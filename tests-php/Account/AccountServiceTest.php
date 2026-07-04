<?php

declare(strict_types=1);

use PHPMax\Api\Account\AvatarType;
use PHPMax\Domain\SyncState;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;
use PHPMax\Transport\TransportInterface;

final class AccountServiceTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake account chunks left');
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

final class AccountTestStore implements SessionStoreInterface
{
    /** @var list<array{0: string, 1: string}> */
    public $updated = [];

    public function saveSession(SessionInfo $sessionInfo): void
    {
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
        $this->updated[] = [$oldToken, $newToken];
    }

    public function loadSession(): ?SessionInfo
    {
        return null;
    }

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo
    {
        return null;
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        return null;
    }

    public function deleteSession(string $token): void
    {
    }

    public function deleteAllSessions(): void
    {
    }

    public function close(): void
    {
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $folderUpdate = static function (string $id, string $title, int $sync): array {
        return [
            'foldersOrder' => [$id],
            'folder' => [
                'sourceId' => 1,
                'include' => [10, 20],
                'options' => [],
                'updateTime' => 100,
                'id' => $id,
                'filters' => [],
                'title' => $title,
            ],
            'folderSync' => $sync,
        ];
    };
    $frameChunks = static function (array $payload, int $opcode, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, Command::RESPONSE));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (AccountServiceTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $chunks = array_merge(
        $frameChunks(['url' => 'https://upload.test/profile'], Opcode::PHOTO_UPLOAD, 0),
        $frameChunks(['profile' => [
            'contact' => ['id' => 77, 'names' => [['name' => 'Me', 'type' => 'NICK']]],
            'profileOptions' => ['showPhone' => false],
        ]], Opcode::PROFILE, 1),
        $frameChunks($folderUpdate('created-folder', 'Created', 101), Opcode::FOLDERS_UPDATE, 2),
        $frameChunks([
            'foldersOrder' => ['created-folder'],
            'folders' => [$folderUpdate('created-folder', 'Created', 101)['folder']],
            'allFilterExcludeFolders' => [],
            'folderSync' => 101,
        ], Opcode::FOLDERS_GET, 3),
        $frameChunks($folderUpdate('folder-1', 'Updated', 102), Opcode::FOLDERS_UPDATE, 4),
        $frameChunks($folderUpdate('folder-1', 'Deleted', 103), Opcode::FOLDERS_DELETE, 5),
        $frameChunks(['token' => 'new-token'], Opcode::SESSIONS_CLOSE, 6),
        $frameChunks([], Opcode::LOGOUT, 7)
    );

    $transport = new AccountServiceTestTransport($chunks);
    $store = new AccountTestStore();
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, 1.0, $store);
    $service = $app->api()->account;

    $assertSame('https://upload.test/profile', $service->requestProfilePhotoUploadUrl());
    $assertSame(['count' => 1, 'profile' => true], $decodeSent($transport, 0)->payload);

    $assertSame(true, $service->changeProfile('First', 'Last', 'About', 'photo-token'));
    $assertSame([
        'firstName' => 'First',
        'lastName' => 'Last',
        'description' => 'About',
        'photoToken' => 'photo-token',
        'avatarType' => AvatarType::USER_AVATAR,
    ], $decodeSent($transport, 1)->payload);
    $assertSame(77, $service->profile()->contact->id);
    $assertSame(77, $app->me()->contact->id, 'changeProfile must update App profile state');
    $assertSame(77, $app->api()->users->getCachedUser(77)->id, 'changeProfile must update user cache');
    $assertSame(77, $app->cachedUser(77)->id, 'changeProfile must update shared App user cache');

    $created = $service->createFolder('Created', [10, 20], [['type' => 'unread']]);
    $createPayload = $decodeSent($transport, 2)->payload;
    $assert(strlen($createPayload['id']) >= 32, 'createFolder must generate a folder id');
    $assertSame('Created', $createPayload['title']);
    $assertSame([10, 20], $createPayload['include']);
    $assertSame([['type' => 'unread']], $createPayload['filters']);
    $assertSame(101, $created->folderSync);
    $assertSame('created-folder', $created->folder->id);

    $folders = $service->getFolders(101);
    $assertSame(['folderSync' => 101], $decodeSent($transport, 3)->payload);
    $assertSame('created-folder', $folders->folders[0]->id);
    $assertSame(101, $folders->folderSync);

    $updated = $service->updateFolder('folder-1', 'Updated', [30], [], [['pinned' => true]]);
    $assertSame([
        'id' => 'folder-1',
        'title' => 'Updated',
        'include' => [30],
        'filters' => [],
        'options' => [['pinned' => true]],
    ], $decodeSent($transport, 4)->payload);
    $assertSame(102, $updated->folderSync);

    $deleted = $service->deleteFolder('folder-1');
    $assertSame(['folderIds' => ['folder-1']], $decodeSent($transport, 5)->payload);
    $assertSame(103, $deleted->folderSync);

    $assertSame(false, $service->closeAllSessions(), 'No current session means no close request');
    $app->setSession(new SessionInfo([
        'token' => 'old-token',
        'deviceId' => 'device',
        'phone' => '+79990000000',
        'mtInstanceId' => 'mt',
        'sync' => new SyncState([
            'chatsSync' => 11,
            'contactsSync' => 22,
            'draftsSync' => 33,
            'presenceSync' => 44,
            'configHash' => 'hash-before-rotation',
        ]),
    ]));
    $assertSame(true, $service->closeAllSessions());
    $assertSame([], $decodeSent($transport, 6)->payload);
    $assertSame([['old-token', 'new-token']], $store->updated);
    $rotatedSession = $app->session();
    $assert($rotatedSession instanceof SessionInfo, 'closeAllSessions must keep a current session after token rotation');
    $assertSame('new-token', $rotatedSession->token);
    $assertSame('device', $rotatedSession->deviceId);
    $assertSame('+79990000000', $rotatedSession->phone);
    $assertSame('mt', $rotatedSession->mtInstanceId);
    $assert($rotatedSession->sync instanceof SyncState, 'closeAllSessions must preserve sync state');
    $assertSame(11, $rotatedSession->sync->chatsSync);
    $assertSame(22, $rotatedSession->sync->contactsSync);
    $assertSame(33, $rotatedSession->sync->draftsSync);
    $assertSame(44, $rotatedSession->sync->presenceSync);
    $assertSame('hash-before-rotation', $rotatedSession->sync->configHash);

    $assertSame(true, $service->logout());
    $assertSame([], $decodeSent($transport, 7)->payload);

    $makeService = static function (array $chunks) use ($protocol): array {
        $transport = new AccountServiceTestTransport($chunks);
        $manager = new ConnectionManager($transport, $protocol);
        $manager->open();
        $app = new App($manager, 1.0);

        return [$transport, $app->api()->account];
    };

    [, $emptyCreateService] = $makeService($frameChunks([], Opcode::FOLDERS_UPDATE, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyCreateService): void {
        $emptyCreateService->createFolder('Broken', [1]);
    }, 'createFolder must require a folder update payload like PyMax require_payload_model');

    [, $emptyListService] = $makeService($frameChunks([], Opcode::FOLDERS_GET, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyListService): void {
        $emptyListService->getFolders();
    }, 'getFolders must require a folder list payload like PyMax require_payload_model');

    [, $emptyUpdateService] = $makeService($frameChunks([], Opcode::FOLDERS_UPDATE, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyUpdateService): void {
        $emptyUpdateService->updateFolder('folder-1', 'Broken');
    }, 'updateFolder must require a folder update payload like PyMax require_payload_model');

    [, $emptyDeleteService] = $makeService($frameChunks([], Opcode::FOLDERS_DELETE, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyDeleteService): void {
        $emptyDeleteService->deleteFolder('folder-1');
    }, 'deleteFolder must require a folder update payload like PyMax require_payload_model');
};
