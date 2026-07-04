<?php

declare(strict_types=1);

use PHPMax\Api\Messages\ItemType;
use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Formatting\Formatter;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class MessageTestTransport implements TransportInterface
{
    /** @var list<string> */
    private $chunks;
    /** @var list<string> */
    public $sent = [];
    /** @var bool */
    private $connected = false;

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
            throw new RuntimeException('No fake message chunks left');
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
    [$clean, $entities] = Formatter::formatMarkdown('# Title' . "\n" . '> Quote' . "\n" . 'Hi 😀 **ok** and [site](https://example.com)');
    $assertSame("Title\nQuote\nHi 😀 ok and site", $clean);
    $assertSame('HEADING', $entities[0]->type);
    $assertSame('QUOTE', $entities[1]->type);
    $assertSame('STRONG', $entities[2]->type);
    $assertSame(18, $entities[2]->from, 'UTF-16 offset must count emoji as two code units');
    $assertSame('LINK', $entities[3]->type);
    $assertSame('https://example.com', $entities[3]->attributes->url);

    $protocol = new TcpProtocol();
    $frameChunks = static function (array $payload, int $opcode, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, Command::RESPONSE));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (MessageTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $chunks = array_merge(
        $frameChunks(['id' => 55, 'chatId' => 100, 'time' => 123, 'type' => 'USER', 'text' => 'Hello bold'], Opcode::MSG_SEND, 0),
        $frameChunks(['id' => 56, 'chatId' => 200, 'time' => 124, 'type' => 'USER', 'text' => 'forward'], Opcode::MSG_SEND, 1),
        $frameChunks(['messages' => [
            ['id' => 57, 'time' => 125, 'type' => 'USER', 'text' => 'one'],
            ['id' => 58, 'chatId' => 100, 'time' => 126, 'type' => 'USER', 'text' => 'two'],
        ]], Opcode::MSG_GET, 2),
        $frameChunks(['message' => ['id' => 57, 'time' => 127, 'type' => 'USER', 'text' => 'edited', 'status' => 'EDITED']], Opcode::MSG_EDIT, 3),
        $frameChunks(['messages' => [
            ['id' => 59, 'chatId' => 100, 'time' => 128, 'type' => 'USER', 'text' => 'history'],
        ]], Opcode::CHAT_HISTORY, 4),
        $frameChunks([], Opcode::MSG_DELETE, 5),
        $frameChunks(['reactionInfo' => ['totalCount' => 1, 'counters' => [['count' => 1, 'reaction' => '👍']], 'yourReaction' => '👍']], Opcode::MSG_REACTION, 6),
        $frameChunks(['messagesReactions' => ['57' => ['totalCount' => 1, 'counters' => []]]], Opcode::MSG_GET_REACTIONS, 7),
        $frameChunks(['reactionInfo' => ['totalCount' => 0, 'counters' => []]], Opcode::MSG_CANCEL_REACTION, 8),
        $frameChunks(['unread' => 0, 'mark' => 999], Opcode::CHAT_MARK, 9),
        $frameChunks(['EXTERNAL' => false, 'cache' => true, 'dynamicVideoUrl' => 'https://cdn.test/video.mp4'], Opcode::VIDEO_PLAY, 10),
        $frameChunks(['unsafe' => false, 'url' => 'https://cdn.test/file.pdf'], Opcode::FILE_DOWNLOAD, 11)
    );
    $transport = new MessageTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, 1.0);
    $service = $app->api()->messages;

    $sentMessage = $service->sendMessage(100, 'Hello **bold**', 44, [['photoToken' => 'photo-token']], false);
    $assertSame(55, $sentMessage->id);
    $sendPayload = $decodeSent($transport, 0)->payload;
    $assertSame(Opcode::MSG_SEND, $decodeSent($transport, 0)->opcode);
    $assertSame(100, $sendPayload['chatId']);
    $assertSame(false, $sendPayload['notify']);
    $assertSame('Hello bold', $sendPayload['message']['text']);
    $assertSame(['type' => 'REPLY', 'messageId' => 44], $sendPayload['message']['link']);
    $assertSame('STRONG', $sendPayload['message']['elements'][0]['type']);
    $assertSame('photo-token', $sendPayload['message']['attaches'][0]['photoToken']);

    $forwarded = $service->forwardMessage(200, 116742887450236083, 100, false);
    $assertSame(56, $forwarded->id);
    $forwardPayload = $decodeSent($transport, 1)->payload;
    $assertSame(-abs($forwardPayload['message']['cid']), $forwardPayload['message']['cid']);
    $assertSame('116742887450236083', $forwardPayload['message']['link']['messageId']);
    $assertSame(100, $forwardPayload['message']['link']['chatId']);
    $assertSame(false, $forwardPayload['notify']);

    $messages = $service->getMessages(100, [57, 58]);
    $assertSame([57, 58], [$messages[0]->id, $messages[1]->id]);
    $assertSame(100, $messages[0]->chatId, 'Missing chatId must be restored from request');
    $assertSame(['chatId' => 100, 'messageIds' => [57, 58]], $decodeSent($transport, 2)->payload);

    $edited = $service->editMessage(100, 57, 'edited **text**');
    $assertSame('EDITED', $edited->status);
    $assertSame(100, $edited->chatId);
    $editPayload = $decodeSent($transport, 3)->payload;
    $assertSame('edited text', $editPayload['text']);
    $assertSame('STRONG', $editPayload['elements'][0]['type']);

    $history = $service->fetchHistory(100, 0, 2, 0, 0, 123, ItemType::DELAYED, true, true, true);
    $assertSame(59, $history[0]->id);
    $historyPayload = $decodeSent($transport, 4)->payload;
    $assertSame(123, $historyPayload['from']);
    $assertSame(ItemType::DELAYED, $historyPayload['itemType']);
    $assertSame(true, $historyPayload['getChat']);

    $assertSame(true, $service->deleteMessage(100, [57], false));
    $assertSame(['chatId' => 100, 'messageIds' => [57], 'forMe' => false], $decodeSent($transport, 5)->payload);

    $reaction = $service->addReaction(100, '57', '👍');
    $assertSame(1, $reaction->totalCount);
    $assertSame('👍', $decodeSent($transport, 6)->payload['reaction']['id']);

    $reactions = $service->getReactions(100, ['57']);
    $assertSame(1, $reactions['57']->totalCount);

    $removed = $service->removeReaction(100, '57');
    $assertSame(0, $removed->totalCount);

    $read = $service->readMessage(57, 100);
    $assertSame(0, $read->unread);
    $assertSame('READ_MESSAGE', $decodeSent($transport, 9)->payload['type']);

    $video = $service->getVideoById(100, '57', 700);
    $assertSame('https://cdn.test/video.mp4', $video->url);
    $assertSame(false, $video->external);
    $assertSame(true, $video->cache);
    $videoPayload = $decodeSent($transport, 10)->payload;
    $assertSame(['chatId' => 100, 'messageId' => '57', 'videoId' => 700], $videoPayload);

    $file = $service->getFileById(100, 57, 800);
    $assertSame('https://cdn.test/file.pdf', $file->url);
    $assertSame(false, $file->unsafe);
    $filePayload = $decodeSent($transport, 11)->payload;
    $assertSame(['chatId' => 100, 'messageId' => 57, 'fileId' => 800], $filePayload);

    $makeService = static function (array $chunks) use ($protocol): array {
        $transport = new MessageTestTransport($chunks);
        $manager = new ConnectionManager($transport, $protocol);
        $manager->open();
        $app = new App($manager, 1.0);

        return [$transport, $app->api()->messages];
    };

    [, $emptyResponseService] = $makeService(array_merge(
        $frameChunks([], Opcode::CHAT_MARK, 0),
        $frameChunks([], Opcode::VIDEO_PLAY, 1),
        $frameChunks([], Opcode::FILE_DOWNLOAD, 2)
    ));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyResponseService): void {
        $emptyResponseService->readMessage(57, 100);
    }, 'readMessage must require a payload like PyMax require_payload_model');
    $assertSame(null, $emptyResponseService->getVideoById(100, '57', 700), 'getVideoById must mirror PyMax parse_payload_model null path');
    $assertSame(null, $emptyResponseService->getFileById(100, '57', 800), 'getFileById must mirror PyMax parse_payload_model null path');

    [, $strictResponseService] = $makeService(array_merge(
        $frameChunks([], Opcode::MSG_SEND, 0),
        $frameChunks([], Opcode::MSG_SEND, 1),
        $frameChunks([], Opcode::MSG_EDIT, 2),
        $frameChunks(['unexpected' => []], Opcode::MSG_EDIT, 3)
    ));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($strictResponseService): void {
        $strictResponseService->sendMessage(100, 'missing response');
    }, 'sendMessage must require a payload like PyMax require_payload_model');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($strictResponseService): void {
        $strictResponseService->forwardMessage(100, 57);
    }, 'forwardMessage must require a payload like PyMax require_payload_model');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($strictResponseService): void {
        $strictResponseService->editMessage(100, 57, 'missing edit response');
    }, 'editMessage must require a message payload item like PyMax require_payload_item_model');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($strictResponseService): void {
        $strictResponseService->editMessage(100, 57, 'missing message item');
    }, 'editMessage must reject responses without message item');

    [, $badMessageListService] = $makeService(array_merge(
        $frameChunks(['messages' => [123]], Opcode::MSG_GET, 0),
        $frameChunks(['messages' => [123]], Opcode::CHAT_HISTORY, 1)
    ));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badMessageListService): void {
        $badMessageListService->getMessages(100, [57]);
    }, 'Malformed message list items must fail fast like PyMax parse_payload_list');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($badMessageListService): void {
        $badMessageListService->fetchHistory(100);
    }, 'Malformed history message list items must fail fast like PyMax parse_payload_list');

    [, $reactionEdgeService] = $makeService(array_merge(
        $frameChunks(['reactionInfo' => []], Opcode::MSG_REACTION, 0),
        $frameChunks(['reactionInfo' => 123], Opcode::MSG_CANCEL_REACTION, 1),
        $frameChunks(['messagesReactions' => ['57' => 123]], Opcode::MSG_GET_REACTIONS, 2),
        $frameChunks(['messagesReactions' => [['totalCount' => 1, 'counters' => []]]], Opcode::MSG_GET_REACTIONS, 3)
    ));
    $assertSame(null, $reactionEdgeService->addReaction(100, '57', '👍'), 'Empty reactionInfo must mirror PyMax optional falsey path');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($reactionEdgeService): void {
        $reactionEdgeService->removeReaction(100, '57');
    }, 'Malformed reactionInfo must fail fast like PyMax model_validate');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($reactionEdgeService): void {
        $reactionEdgeService->getReactions(100, ['57']);
    }, 'Malformed messagesReactions values must fail fast');
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($reactionEdgeService): void {
        $reactionEdgeService->getReactions(100, ['57']);
    }, 'messagesReactions must be a map keyed by message id');

    $clientChunks = array_merge(
        $frameChunks(['id' => 70, 'chatId' => 300, 'time' => 130, 'type' => 'USER', 'text' => 'forwarded'], Opcode::MSG_SEND, 0),
        $frameChunks(['message' => ['id' => 71, 'chatId' => 300, 'time' => 131, 'type' => 'USER', 'text' => 'edited']], Opcode::MSG_EDIT, 1),
        $frameChunks(['messages' => [
            ['id' => 72, 'chatId' => 300, 'time' => 132, 'type' => 'USER', 'text' => 'history'],
        ]], Opcode::CHAT_HISTORY, 2),
        $frameChunks([], Opcode::MSG_DELETE, 3),
        $frameChunks([], Opcode::CHAT_UPDATE, 4),
        $frameChunks(['reactionInfo' => ['totalCount' => 1, 'counters' => []]], Opcode::MSG_REACTION, 5),
        $frameChunks(['messagesReactions' => ['70' => ['totalCount' => 2, 'counters' => []]]], Opcode::MSG_GET_REACTIONS, 6),
        $frameChunks(['reactionInfo' => ['totalCount' => 0, 'counters' => []]], Opcode::MSG_CANCEL_REACTION, 7),
        $frameChunks(['unread' => 0, 'mark' => 1000], Opcode::CHAT_MARK, 8),
        $frameChunks(['EXTERNAL' => false, 'cache' => true, 'dynamicVideoUrl' => 'https://cdn.test/client-video.mp4'], Opcode::VIDEO_PLAY, 9),
        $frameChunks(['unsafe' => false, 'url' => 'https://cdn.test/client-file.pdf'], Opcode::FILE_DOWNLOAD, 10)
    );
    $clientTransport = new MessageTestTransport($clientChunks);
    $clientManager = new ConnectionManager($clientTransport, $protocol);
    $clientManager->open();
    $client = new Client(new ClientOptions(['requestTimeout' => 1.0]), $clientManager);

    $clientForwarded = $client->forwardMessage(300, 'source-message', 100, false);
    $assertSame(70, $clientForwarded->id);
    $assertSame(Opcode::MSG_SEND, $decodeSent($clientTransport, 0)->opcode);
    $assertSame('source-message', $decodeSent($clientTransport, 0)->payload['message']['link']['messageId']);

    $clientEdited = $client->editMessage(300, 71, 'client **edit**');
    $assertSame(71, $clientEdited->id);
    $assertSame(Opcode::MSG_EDIT, $decodeSent($clientTransport, 1)->opcode);
    $assertSame('client edit', $decodeSent($clientTransport, 1)->payload['text']);

    $clientHistory = $client->fetchHistory(300, 0, 1, 0, 0, 555, ItemType::REGULAR, false, true, false);
    $assertSame(72, $clientHistory[0]->id);
    $assertSame(Opcode::CHAT_HISTORY, $decodeSent($clientTransport, 2)->opcode);
    $assertSame(555, $decodeSent($clientTransport, 2)->payload['from']);

    $assertSame(true, $client->deleteMessage(300, [70], true));
    $assertSame(Opcode::MSG_DELETE, $decodeSent($clientTransport, 3)->opcode);
    $assertSame(['chatId' => 300, 'messageIds' => [70], 'forMe' => true], $decodeSent($clientTransport, 3)->payload);

    $assertSame(true, $client->pinMessage(300, 70, false));
    $assertSame(Opcode::CHAT_UPDATE, $decodeSent($clientTransport, 4)->opcode);
    $assertSame(false, $decodeSent($clientTransport, 4)->payload['notifyPin']);

    $clientReaction = $client->addReaction(300, '70', '🔥');
    $assertSame(1, $clientReaction->totalCount);
    $assertSame(Opcode::MSG_REACTION, $decodeSent($clientTransport, 5)->opcode);
    $assertSame('🔥', $decodeSent($clientTransport, 5)->payload['reaction']['id']);

    $clientReactions = $client->getReactions(300, ['70']);
    $assertSame(2, $clientReactions['70']->totalCount);
    $assertSame(Opcode::MSG_GET_REACTIONS, $decodeSent($clientTransport, 6)->opcode);

    $clientRemoved = $client->removeReaction(300, '70');
    $assertSame(0, $clientRemoved->totalCount);
    $assertSame(Opcode::MSG_CANCEL_REACTION, $decodeSent($clientTransport, 7)->opcode);

    $clientRead = $client->readMessage('70', 300);
    $assertSame(0, $clientRead->unread);
    $assertSame(Opcode::CHAT_MARK, $decodeSent($clientTransport, 8)->opcode);
    $assertSame('70', $decodeSent($clientTransport, 8)->payload['messageId']);

    $clientVideo = $client->getVideoById(300, '70', 900);
    $assertSame('https://cdn.test/client-video.mp4', $clientVideo->url);
    $assertSame(Opcode::VIDEO_PLAY, $decodeSent($clientTransport, 9)->opcode);

    $clientFile = $client->getFileById(300, '70', 901);
    $assertSame('https://cdn.test/client-file.pdf', $clientFile->url);
    $assertSame(Opcode::FILE_DOWNLOAD, $decodeSent($clientTransport, 10)->opcode);
};
