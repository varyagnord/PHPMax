<?php

declare(strict_types=1);

use PHPMax\Api\Chats\ChatMemberOperation;
use PHPMax\Api\Chats\ChatOption;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class ChatServiceTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake chat chunks left');
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
    $chat = static function (int $id, string $title = 'chat'): array {
        return [
            'id' => $id,
            'type' => 'CHAT',
            'status' => 'ACTIVE',
            'owner' => 1,
            'title' => $title,
            'participantsCount' => 3,
            'lastEventTime' => 1000 + $id,
        ];
    };
    $frameChunks = static function (array $payload, int $opcode, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, Command::RESPONSE));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (ChatServiceTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $chunks = array_merge(
        $frameChunks([
            'id' => 700,
            'chatId' => 10,
            'time' => 111,
            'type' => 'SYSTEM',
            'text' => 'created',
            'chat' => $chat(10, 'created'),
        ], Opcode::MSG_SEND, 0),
        $frameChunks(['chat' => $chat(10, 'invited')], Opcode::CHAT_MEMBERS_UPDATE, 1),
        $frameChunks(['chat' => $chat(10, 'removed')], Opcode::CHAT_MEMBERS_UPDATE, 2),
        $frameChunks(['chat' => $chat(10, 'settings')], Opcode::CHAT_UPDATE, 3),
        $frameChunks(['chat' => $chat(10, 'profile')], Opcode::CHAT_UPDATE, 4),
        $frameChunks(['chat' => $chat(20, 'joined')], Opcode::CHAT_JOIN, 5),
        $frameChunks(['chat' => $chat(21, 'resolved')], Opcode::LINK_INFO, 6),
        $frameChunks(['chat' => array_merge($chat(10, 'reworked'), ['link' => 'join/new'])], Opcode::CHAT_UPDATE, 7),
        $frameChunks(['chats' => [$chat(30, 'loaded')]], Opcode::CHAT_INFO, 8),
        $frameChunks(['chats' => [$chat(40, 'page')]], Opcode::CHATS_LIST, 9),
        $frameChunks(['members' => [[
            'contact' => ['id' => 5, 'names' => []],
            'presence' => ['status' => 2, 'seen' => 999],
        ]]], Opcode::CHAT_MEMBERS, 10),
        $frameChunks(['chat' => $chat(10, 'confirmed')], Opcode::CHAT_MEMBERS_UPDATE, 11),
        $frameChunks(['chat' => $chat(10, 'declined')], Opcode::CHAT_MEMBERS_UPDATE, 12),
        $frameChunks([], Opcode::CHAT_LEAVE, 13),
        $frameChunks([], Opcode::CHAT_DELETE, 14)
    );

    $transport = new ChatServiceTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, 1.0);
    $service = $app->api()->chats;

    $created = $service->createGroup('Team', [1, 2], false);
    $assertSame(10, $created[0]->id);
    $assertSame(700, $created[1]->id);
    $assertSame(10, $app->cachedChat(10)->id);
    $createPayload = $decodeSent($transport, 0)->payload;
    $assertSame(Opcode::MSG_SEND, $decodeSent($transport, 0)->opcode);
    $assertSame(false, $createPayload['notify']);
    $assertSame('CONTROL', $createPayload['message']['attaches'][0]['_type']);
    $assertSame('new', $createPayload['message']['attaches'][0]['event']);
    $assertSame('CHAT', $createPayload['message']['attaches'][0]['chatType']);
    $assertSame('Team', $createPayload['message']['attaches'][0]['title']);
    $assertSame([1, 2], $createPayload['message']['attaches'][0]['userIds']);

    $invited = $service->inviteUsersToGroup(10, [3, 4], false);
    $assertSame('invited', $invited->title);
    $assertSame('invited', $app->cachedChat(10)->title);
    $assertSame([
        'chatId' => 10,
        'userIds' => [3, 4],
        'showHistory' => false,
        'operation' => ChatMemberOperation::ADD,
    ], $decodeSent($transport, 1)->payload);

    $assertSame(true, $service->removeUsersFromGroup(10, [4], 86400));
    $assertSame([
        'chatId' => 10,
        'userIds' => [4],
        'operation' => ChatMemberOperation::REMOVE,
        'cleanMsgPeriod' => 86400,
    ], $decodeSent($transport, 2)->payload);

    $service->changeGroupSettings(10, true, null, false, null, true);
    $settingsPayload = $decodeSent($transport, 3)->payload;
    $assertSame(10, $settingsPayload['chatId']);
    $assertSame([
        ChatOption::ALL_CAN_PIN_MESSAGE => true,
        ChatOption::ONLY_ADMIN_CAN_ADD_MEMBER => false,
        ChatOption::MEMBERS_CAN_SEE_PRIVATE_LINK => true,
    ], $settingsPayload['options']);

    $service->changeGroupProfile(10, 'New name', 'Description');
    $assertSame([
        'chatId' => 10,
        'theme' => 'New name',
        'description' => 'Description',
    ], $decodeSent($transport, 4)->payload);

    $joined = $service->joinGroup('https://max.ru/join/abc');
    $assertSame(20, $joined->id);
    $assertSame(['link' => 'join/abc'], $decodeSent($transport, 5)->payload);
    $assertThrows(InvalidArgumentException::class, static function () use ($service): void {
        $service->joinGroup('bad-link');
    });

    $resolved = $service->resolveGroupByLink('https://max.ru/join/xyz');
    $assertSame(21, $resolved->id);
    $assertSame(['link' => 'join/xyz'], $decodeSent($transport, 6)->payload);
    $assertThrows(InvalidArgumentException::class, static function () use ($service): void {
        $service->resolveGroupByLink('bad-link');
    });

    $reworked = $service->reworkInviteLink(10);
    $assertSame('join/new', $reworked->link);
    $assertSame(['revokePrivateLink' => true, 'chatId' => 10], $decodeSent($transport, 7)->payload);

    $chats = $service->getChats([10, 30]);
    $assertSame([10, 30], [$chats[0]->id, $chats[1]->id]);
    $assertSame(['chatIds' => [30]], $decodeSent($transport, 8)->payload, 'Cached chat must not be requested again');

    $page = $service->fetchChats(123);
    $assertSame(40, $page[0]->id);
    $assertSame(40, $app->cachedChat(40)->id);
    $assertSame(['marker' => 123], $decodeSent($transport, 9)->payload);

    $members = $service->getJoinRequests(10, 5);
    $assertSame(5, $members[0]->contact->id);
    $assertSame(2, $members[0]->presence->status);
    $assertSame(['chatId' => 10, 'type' => 'JOIN_REQUEST', 'count' => 5], $decodeSent($transport, 10)->payload);

    $confirmed = $service->confirmJoinRequest(10, 6, true);
    $assertSame('confirmed', $confirmed->title);
    $assertSame([
        'chatId' => 10,
        'userIds' => [6],
        'type' => 'JOIN_REQUEST',
        'showHistory' => true,
        'operation' => ChatMemberOperation::ADD,
    ], $decodeSent($transport, 11)->payload);

    $declined = $service->declineJoinRequests(10, [7, 8]);
    $assertSame('declined', $declined->title);
    $assertSame([
        'chatId' => 10,
        'userIds' => [7, 8],
        'type' => 'JOIN_REQUEST',
        'operation' => ChatMemberOperation::REMOVE,
    ], $decodeSent($transport, 12)->payload);

    $service->leaveGroup(10);
    $assertSame(['chatId' => 10], $decodeSent($transport, 13)->payload);
    $assertSame(null, $app->cachedChat(10));

    $service->deleteChat(30, 777, false);
    $assertSame(['chatId' => 30, 'lastEventTime' => 777, 'forAll' => false], $decodeSent($transport, 14)->payload);
    $assertSame(null, $app->cachedChat(30));

    $makeService = static function (array $chunks) use ($protocol): array {
        $transport = new ChatServiceTestTransport($chunks);
        $manager = new ConnectionManager($transport, $protocol);
        $manager->open();
        $app = new App($manager, 1.0);

        return [$transport, $app->api()->chats];
    };

    [$channelTransport, $channelService] = $makeService($frameChunks(['chat' => $chat(50, 'channel')], Opcode::CHAT_JOIN, 0));
    $channel = $channelService->joinChannel('channel/invite');
    $assertSame(50, $channel->id);
    $assertSame(['link' => 'channel/invite'], $decodeSent($channelTransport, 0)->payload);

    [$zeroMarkerTransport, $zeroMarkerService] = $makeService($frameChunks(['chats' => [$chat(60, 'zero-marker')]], Opcode::CHATS_LIST, 0));
    $beforeMarker = (int) floor(microtime(true) * 1000);
    $zeroMarkerPage = $zeroMarkerService->fetchChats(0);
    $afterMarker = (int) floor(microtime(true) * 1000);
    $zeroMarkerPayload = $decodeSent($zeroMarkerTransport, 0)->payload;
    $assertSame(60, $zeroMarkerPage[0]->id);
    $assert($zeroMarkerPayload['marker'] !== 0, 'fetchChats(0) must mirror PyMax marker-or-now behavior');
    $assert(
        $zeroMarkerPayload['marker'] >= $beforeMarker && $zeroMarkerPayload['marker'] <= $afterMarker,
        'fetchChats(0) must send the current timestamp marker'
    );

    [, $badChatListService] = $makeService($frameChunks(['chats' => [123]], Opcode::CHATS_LIST, 0));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badChatListService): void {
        $badChatListService->fetchChats(123);
    }, 'Malformed chat list items must fail fast like PyMax parse_payload_list');

    $emptyChatChunks = array_merge(
        $frameChunks(['chat' => []], Opcode::MSG_SEND, 0),
        $frameChunks(['chat' => []], Opcode::CHAT_MEMBERS_UPDATE, 1),
        $frameChunks(['chat' => []], Opcode::CHAT_MEMBERS_UPDATE, 2),
        $frameChunks(['chat' => []], Opcode::CHAT_MEMBERS_UPDATE, 3)
    );
    $emptyChatTransport = new ChatServiceTestTransport($emptyChatChunks);
    $emptyChatManager = new ConnectionManager($emptyChatTransport, $protocol);
    $emptyChatManager->open();
    $emptyChatApp = new App($emptyChatManager, 1.0);
    $emptyChatService = $emptyChatApp->api()->chats;
    $emptyChatService->cacheExternalChat(\PHPMax\Domain\Chat::fromArray($chat(80, 'existing')));

    $assertSame(null, $emptyChatService->createGroup('Empty chat item'));
    $assertSame(null, $emptyChatService->inviteUsersToGroup(80, [1]));
    $assertSame(true, $emptyChatService->removeUsersFromGroup(80, [1], 0));
    $assertSame(null, $emptyChatService->confirmJoinRequest(80, 1));
    $assertSame('existing', $emptyChatApp->cachedChat(80)->title, 'Empty optional chat item must not overwrite cached chat');
};
