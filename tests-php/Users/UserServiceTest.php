<?php

declare(strict_types=1);

use PHPMax\Api\Users\ContactAction;
use PHPMax\Domain\ContactInfo;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class UserServiceTestTransport implements TransportInterface
{
    /** @var list<string> */
    private $chunks;
    /** @var bool */
    private $connected = false;
    /** @var list<string> */
    public $sent = [];

    /**
     * @param list<string> $chunks
     */
    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function recv(int $length, float $timeout): string
    {
        $chunk = array_shift($this->chunks);
        if ($chunk === null) {
            throw new RuntimeException('No fake user chunks left');
        }
        if (strlen($chunk) !== $length) {
            throw new RuntimeException('Expected chunk length ' . $length . ', got ' . strlen($chunk));
        }

        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $user = static function (int $id, string $name = 'User'): array {
        return [
            'id' => $id,
            'names' => [['name' => $name . ' ' . $id, 'type' => 'NICK']],
            'accountStatus' => 1,
            'registrationTime' => 100 + $id,
            'country' => 'RU',
            'baseRawUrl' => 'raw-' . $id,
            'baseUrl' => 'base-' . $id,
            'photoId' => $id + 1000,
            'updateTime' => $id + 2000,
            'phone' => '7999000' . $id,
            'status' => 'CONTACT',
            'description' => 'desc',
            'gender' => 1,
            'link' => 'https://max.test/u/' . $id,
            'webApp' => ['url' => 'https://app.test'],
            'menuButton' => ['type' => 'web_app'],
        ];
    };
    $frameChunks = static function (array $payload, int $opcode, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, Command::RESPONSE));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (UserServiceTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $chunks = array_merge(
        $frameChunks(['contacts' => [$user(10), $user(20)]], Opcode::CONTACT_INFO, 0),
        $frameChunks(['contacts' => [$user(30)]], Opcode::CONTACT_INFO, 1),
        $frameChunks(['contact' => $user(40, 'Phone')], Opcode::CONTACT_INFO_BY_PHONE, 2),
        $frameChunks(['contact' => $user(50, 'Added')], Opcode::CONTACT_UPDATE, 3),
        $frameChunks([], Opcode::CONTACT_UPDATE, 4),
        $frameChunks(['sessions' => [[
            'id' => 'session-1',
            'deviceId' => 'device-1',
            'current' => true,
            'userAgent' => 'ua',
            'appVersion' => '1.0',
            'deviceName' => 'Desktop',
            'deviceType' => 'WEB',
            'platform' => 'Linux',
            'ip' => '127.0.0.1',
            'location' => 'Local',
            'created' => 1,
            'updated' => 2,
            'lastActivity' => 3,
            'options' => ['x' => true],
        ]]], Opcode::SESSIONS_INFO, 5),
        $frameChunks(['contacts' => [$user(60, 'Imported')]], Opcode::SYNC, 6),
        $frameChunks(['contact' => $user(10, 'HelperAdd')], Opcode::CONTACT_UPDATE, 7),
        $frameChunks([], Opcode::CONTACT_UPDATE, 8)
    );

    $transport = new UserServiceTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, 1.0);
    $service = $app->api()->users;

    $fetched = $service->fetchUsers([10, 20]);
    $assertSame([10, 20], [$fetched[0]->id, $fetched[1]->id]);
    $assertSame('User 10', $fetched[0]->names[0]->name);
    $assertSame('RU', $fetched[0]->country);
    $assertSame(10, $app->cachedUser(10)->id);
    $assertSame(['contactIds' => [10, 20]], $decodeSent($transport, 0)->payload);

    $users = $service->getUsers([10, 20, 30]);
    $assertSame([10, 20, 30], [$users[0]->id, $users[1]->id, $users[2]->id]);
    $assertSame(['contactIds' => [30]], $decodeSent($transport, 1)->payload, 'getUsers must request only cache misses');
    $assertSame(10, $service->getCachedUser(10)->id);
    $assertSame(10 ^ 20, $service->getChatId(10, 20));

    $byPhone = $service->searchByPhone('+79990000040');
    $assertSame(40, $byPhone->id);
    $assertSame(['phone' => '+79990000040'], $decodeSent($transport, 2)->payload);

    $added = $service->addContact(50);
    $assertSame(50, $added->id);
    $assertSame(['contactId' => 50, 'action' => ContactAction::ADD], $decodeSent($transport, 3)->payload);
    $assertSame(true, $service->removeContact(50));
    $assertSame(['contactId' => 50, 'action' => ContactAction::REMOVE], $decodeSent($transport, 4)->payload);
    $assertSame(null, $service->getCachedUser(50));
    $assertSame(null, $app->cachedUser(50));

    $sessions = $service->getSessions();
    $assertSame('session-1', $sessions[0]->id);
    $assertSame(true, $sessions[0]->current);
    $assertSame('Linux', $sessions[0]->platform);
    $assertSame([], $decodeSent($transport, 5)->payload);

    $imported = $service->importContacts([
        new ContactInfo(['phone' => '+79990000060', 'firstName' => 'Imported']),
    ]);
    $assertSame(60, $imported[0]->id);
    $assertSame([
        'contactList' => [
            '+79990000060' => ['firstName' => 'Imported'],
        ],
    ], $decodeSent($transport, 6)->payload);

    $helperAdded = $fetched[0]->addContact();
    $assertSame(10, $helperAdded->id);
    $assertSame(['contactId' => 10, 'action' => ContactAction::ADD], $decodeSent($transport, 7)->payload);
    $assertSame(true, $fetched[0]->removeContact());
    $assertSame(['contactId' => 10, 'action' => ContactAction::REMOVE], $decodeSent($transport, 8)->payload);
    $assertSame(null, $app->cachedUser(10));
    $assertSame(10 ^ 99, $fetched[0]->getChatId(99));

    $badRemoveTransport = new UserServiceTestTransport($frameChunks([1], Opcode::CONTACT_UPDATE, 0));
    $badRemoveManager = new ConnectionManager($badRemoveTransport, $protocol);
    $badRemoveManager->open();
    $badRemoveApp = new App($badRemoveManager, 1.0);
    $badRemoveService = $badRemoveApp->api()->users;
    $badRemoveService->cacheExternalUser(\PHPMax\Domain\User::fromArray($user(70, 'Cached')));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badRemoveService): void {
        $badRemoveService->removeContact(70);
    }, 'removeContact must require dict CONTACT_UPDATE response like PyMax require_payload_dict');
    $assertSame(70, $badRemoveApp->cachedUser(70)->id, 'Invalid CONTACT_UPDATE response must not clear user cache');

    $makeUserService = static function (array $chunks) use ($protocol): array {
        $transport = new UserServiceTestTransport($chunks);
        $manager = new ConnectionManager($transport, $protocol);
        $manager->open();
        $app = new App($manager, 1.0);

        return [$transport, $app->api()->users];
    };

    [, $badFetchService] = $makeUserService($frameChunks(['contacts' => [123]], Opcode::CONTACT_INFO, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badFetchService): void {
        $badFetchService->fetchUsers([80]);
    }, 'Malformed contacts list items must fail fast like PyMax parse_payload_list');

    [, $badSessionsService] = $makeUserService($frameChunks(['sessions' => [123]], Opcode::SESSIONS_INFO, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badSessionsService): void {
        $badSessionsService->getSessions();
    }, 'Malformed sessions list items must fail fast like PyMax parse_payload_list');

    [, $badImportService] = $makeUserService($frameChunks(['contacts' => [123]], Opcode::SYNC, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badImportService): void {
        $badImportService->importContacts([new ContactInfo(['phone' => '+79990000080'])]);
    }, 'Malformed imported contacts list items must fail fast like PyMax parse_payload_list');
};
