<?php

declare(strict_types=1);

use PHPMax\Api\Chats\ChatMemberOperation;
use PHPMax\Api\Chats\ChatOption;
use PHPMax\Api\Messages\ItemType;
use PHPMax\Api\Binding;
use PHPMax\Api\Uploads\HttpUploaderInterface;
use PHPMax\Api\Uploads\HttpUploadResponse;
use PHPMax\Config\ClientOptions;
use PHPMax\Domain\Chat;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\Message;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class DomainBindingTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake domain binding chunks left');
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

final class DomainBindingFakeUploader implements HttpUploaderInterface
{
    /** @var list<array<string, mixed>> */
    public $multipart = [];
    /** @var list<array<string, mixed>> */
    public $streams = [];

    public function uploadMultipart(
        string $url,
        string $fieldName,
        string $contents,
        string $filename,
        string $contentType
    ): HttpUploadResponse {
        $params = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $photoId = (string) ($params['photoIds'] ?? 'photo');
        $this->multipart[] = [
            'url' => $url,
            'fieldName' => $fieldName,
            'contents' => $contents,
            'filename' => $filename,
            'contentType' => $contentType,
        ];

        return new HttpUploadResponse(200, json_encode([
            'photos' => [
                $photoId => ['token' => 'token-' . $photoId],
            ],
        ]));
    }

    public function uploadStream(
        string $url,
        array $headers,
        iterable $chunks,
        int $contentLength
    ): HttpUploadResponse {
        $this->streams[] = [
            'url' => $url,
            'headers' => $headers,
            'chunks' => is_array($chunks) ? $chunks : iterator_to_array($chunks),
            'contentLength' => $contentLength,
        ];

        return new HttpUploadResponse(200, '');
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $assertThrows(RuntimeException::class, static function (): void {
        Message::fromArray(['id' => 1, 'chatId' => 2, 'time' => 3, 'type' => 'USER'])->reply('x');
    });
    $assertThrows(RuntimeException::class, static function (): void {
        Chat::fromArray(['id' => 1, 'type' => 'DIALOG', 'status' => 'ACTIVE', 'owner' => 2])->answer('x');
    });

    $protocol = new TcpProtocol();
    $message = static function (int $id, int $chatId, string $text = 'text', array $extra = []): array {
        return array_merge([
            'id' => $id,
            'chatId' => $chatId,
            'time' => 1000 + $id,
            'type' => 'USER',
            'text' => $text,
        ], $extra);
    };
    $chat = static function (int $id, string $type = 'CHAT', array $extra = []) use ($message): array {
        return array_merge([
            'id' => $id,
            'type' => $type,
            'status' => 'ACTIVE',
            'owner' => 1,
            'title' => 'chat-' . $id,
            'lastEventTime' => 1700 + $id,
            'pinnedMessage' => $message(77, $id, 'pinned'),
        ], $extra);
    };
    $frameChunks = static function (array $payload, int $opcode, int $seq, int $cmd = Command::RESPONSE) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, $cmd));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (DomainBindingTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $chunks = array_merge(
        $frameChunks(['messages' => [$message(10, 500, 'source')]], Opcode::MSG_GET, 0),
        $frameChunks($message(11, 500, 'reply'), Opcode::MSG_SEND, 1),
        $frameChunks($message(12, 500, 'answer'), Opcode::MSG_SEND, 2),
        $frameChunks($message(13, 600, 'forward'), Opcode::MSG_SEND, 3),
        $frameChunks([], Opcode::CHAT_UPDATE, 4),
        $frameChunks(['message' => $message(10, 500, 'edited', ['status' => 'EDITED'])], Opcode::MSG_EDIT, 5),
        $frameChunks([], Opcode::MSG_DELETE, 6),
        $frameChunks(['unread' => 0, 'mark' => 12345], Opcode::CHAT_MARK, 7),
        $frameChunks(['reactionInfo' => ['totalCount' => 1, 'counters' => [['count' => 1, 'reaction' => '👍']], 'yourReaction' => '👍']], Opcode::MSG_REACTION, 8),
        $frameChunks(['reactionInfo' => ['totalCount' => 0, 'counters' => []]], Opcode::MSG_CANCEL_REACTION, 9),
        $frameChunks(['messagesReactions' => ['10' => ['totalCount' => 2, 'counters' => []]]], Opcode::MSG_GET_REACTIONS, 10),
        $frameChunks(['chats' => [$chat(700)]], Opcode::CHAT_INFO, 11),
        $frameChunks($message(78, 700, 'chat answer'), Opcode::MSG_SEND, 12),
        $frameChunks(['messages' => [$message(79, 700, 'history')]], Opcode::CHAT_HISTORY, 13),
        $frameChunks(['messages' => [$message(80, 700, 'single')]], Opcode::MSG_GET, 14),
        $frameChunks(['messages' => [$message(81, 700, 'many')]], Opcode::MSG_GET, 15),
        $frameChunks([], Opcode::CHAT_UPDATE, 16),
        $frameChunks(['chat' => $chat(700, 'CHAT', ['title' => 'invited'])], Opcode::CHAT_MEMBERS_UPDATE, 17),
        $frameChunks(['chat' => $chat(700, 'CHAT', ['title' => 'removed'])], Opcode::CHAT_MEMBERS_UPDATE, 18),
        $frameChunks(['chat' => $chat(700, 'CHAT', ['title' => 'settings'])], Opcode::CHAT_UPDATE, 19),
        $frameChunks(['chat' => $chat(700, 'CHAT', ['link' => 'join/new'])], Opcode::CHAT_UPDATE, 20),
        $frameChunks([], Opcode::CHAT_DELETE, 21),
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=message-helper'], Opcode::PHOTO_UPLOAD, 22),
        $frameChunks($message(90, 500, 'message photo'), Opcode::MSG_SEND, 23),
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=chat-helper'], Opcode::PHOTO_UPLOAD, 24),
        $frameChunks($message(91, 700, 'chat photo'), Opcode::MSG_SEND, 25),
        $frameChunks(['info' => [['url' => 'https://upload.test/video-helper', 'videoId' => 901, 'token' => 'message-video-token']]], Opcode::VIDEO_UPLOAD, 26),
        $frameChunks(['videoId' => 901], Opcode::NOTIF_ATTACH, 9010, Command::REQUEST),
        $frameChunks($message(93, 500, 'message video'), Opcode::MSG_SEND, 27),
        $frameChunks(['info' => [['url' => 'https://upload.test/file-helper', 'fileId' => 902, 'token' => 'chat-file-token']]], Opcode::FILE_UPLOAD, 28),
        $frameChunks(['fileId' => 902], Opcode::NOTIF_ATTACH, 9020, Command::REQUEST),
        $frameChunks($message(94, 700, 'chat file'), Opcode::MSG_SEND, 29)
    );

    $transport = new DomainBindingTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $uploader = new DomainBindingFakeUploader();
    $app = new App($manager, new ClientOptions([
        'requestTimeout' => 1.0,
        'httpUploader' => $uploader,
    ]));

    $message = $app->api()->messages->getMessage(500, 10);
    $assertSame(10, $message->id);

    $reply = $message->reply('reply **bold**', null, false);
    $assertSame(11, $reply->id);
    $assertSame(['type' => 'REPLY', 'messageId' => 10], $decodeSent($transport, 1)->payload['message']['link']);
    $assertSame(false, $decodeSent($transport, 1)->payload['notify']);

    $answer = $message->answer('answer', 99, null, true);
    $assertSame(12, $answer->id);
    $assertSame(['type' => 'REPLY', 'messageId' => 99], $decodeSent($transport, 2)->payload['message']['link']);

    $forward = $message->forward(600, false);
    $assertSame(13, $forward->id);
    $forwardPayload = $decodeSent($transport, 3)->payload;
    $assertSame(600, $forwardPayload['chatId']);
    $assertSame('10', $forwardPayload['message']['link']['messageId']);
    $assertSame(500, $forwardPayload['message']['link']['chatId']);
    $assertSame(false, $forwardPayload['notify']);

    $assertSame(true, $message->pin(false));
    $assertSame(['chatId' => 500, 'notifyPin' => false, 'pinMessageId' => 10], $decodeSent($transport, 4)->payload);

    $edited = $message->edit('edited');
    $assertSame('EDITED', $edited->status);
    $assertSame(true, $message->delete(true));
    $assertSame(['chatId' => 500, 'messageIds' => [10], 'forMe' => true], $decodeSent($transport, 6)->payload);

    $read = $message->read();
    $assertSame(12345, $read->mark);
    $reaction = $message->react('👍');
    $assertSame(1, $reaction->totalCount);
    $removedReaction = $message->unreact();
    $assertSame(0, $removedReaction->totalCount);
    $reactions = $message->getReactions();
    $assertSame(2, $reactions['10']->totalCount);

    $chat = $app->api()->chats->getChat(700);
    $assertSame(700, $chat->id);
    $assertSame(77, $chat->pinnedMessage->id);
    $assertSame(['chatIds' => [700]], $decodeSent($transport, 11)->payload);
    $assert($chat->isGroup(), 'Loaded chat must be detected as group');
    $assert(!$chat->isDialog(), 'Loaded chat must not be detected as dialog');

    $chatAnswer = $chat->answer('hello', null, null, false);
    $assertSame(78, $chatAnswer->id);
    $assertSame(700, $decodeSent($transport, 12)->payload['chatId']);
    $assertSame(false, $decodeSent($transport, 12)->payload['notify']);

    $history = $chat->history(0, 1, 0, 0, 123, ItemType::DELAYED, true, true, true);
    $assertSame(79, $history[0]->id);
    $historyPayload = $decodeSent($transport, 13)->payload;
    $assertSame(123, $historyPayload['from']);
    $assertSame(ItemType::DELAYED, $historyPayload['itemType']);

    $single = $chat->getMessage(80);
    $assertSame(80, $single->id);
    $many = $chat->getMessages([81]);
    $assertSame(81, $many[0]->id);

    $assertSame(true, $chat->pinMessage(82, false));
    $assertSame(['chatId' => 700, 'notifyPin' => false, 'pinMessageId' => 82], $decodeSent($transport, 16)->payload);

    $invited = $chat->invite([1, 2], false);
    $assertSame('invited', $invited->title);
    $assertSame([
        'chatId' => 700,
        'userIds' => [1, 2],
        'showHistory' => false,
        'operation' => ChatMemberOperation::ADD,
    ], $decodeSent($transport, 17)->payload);

    $assertSame(true, $chat->removeUsers([2], 0));
    $assertSame([
        'chatId' => 700,
        'userIds' => [2],
        'operation' => ChatMemberOperation::REMOVE,
        'cleanMsgPeriod' => 0,
    ], $decodeSent($transport, 18)->payload);

    $chat->updateSettings(true, null, false, null, null);
    $assertSame([
        ChatOption::ALL_CAN_PIN_MESSAGE => true,
        ChatOption::ONLY_ADMIN_CAN_ADD_MEMBER => false,
    ], $decodeSent($transport, 19)->payload['options']);

    $reworked = $chat->reworkInviteLink();
    $assertSame('join/new', $reworked->link);

    $chat->delete(false);
    $assertSame(['chatId' => 700, 'lastEventTime' => 2400, 'forAll' => false], $decodeSent($transport, 21)->payload);

    $messagePhotoAnswer = $message->answer('message photo', null, [Photo::fromRaw('image-bytes', 'answer.png')]);
    $assertSame(90, $messagePhotoAnswer->id);
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 22)->payload);
    $assertSame([
        '_type' => 'PHOTO',
        'photoToken' => 'token-message-helper',
    ], $decodeSent($transport, 23)->payload['message']['attaches'][0]);

    $chatPhotoAnswer = $chat->answer('chat photo', null, [Photo::fromRaw('image-bytes', 'chat.png')]);
    $assertSame(91, $chatPhotoAnswer->id);
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 24)->payload);
    $assertSame([
        '_type' => 'PHOTO',
        'photoToken' => 'token-chat-helper',
    ], $decodeSent($transport, 25)->payload['message']['attaches'][0]);
    $assertSame('image.png', $uploader->multipart[0]['filename']);
    $assertSame('image.png', $uploader->multipart[1]['filename']);
    $assertSame('image/png', $uploader->multipart[0]['contentType']);

    $messageVideoAnswer = $message->answer('message video', null, [Video::fromRaw('video-bytes', 'answer.mp4')]);
    $assertSame(93, $messageVideoAnswer->id);
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 26)->payload);
    $assertSame('https://upload.test/video-helper', $uploader->streams[0]['url']);
    $assertSame('attachment; filename=answer.mp4', $uploader->streams[0]['headers']['Content-Disposition']);
    $assertSame('0-10/11', $uploader->streams[0]['headers']['Content-Range']);
    $assertSame(['video-bytes'], $uploader->streams[0]['chunks']);
    $assertSame([
        '_type' => 'VIDEO',
        'videoId' => 901,
        'token' => 'message-video-token',
    ], $decodeSent($transport, 27)->payload['message']['attaches'][0]);

    $chatFileAnswer = $chat->answer('chat file', null, [File::fromRaw('file-bytes', 'chat.pdf')]);
    $assertSame(94, $chatFileAnswer->id);
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 28)->payload);
    $assertSame('https://upload.test/file-helper', $uploader->streams[1]['url']);
    $assertSame('attachment; filename=chat.pdf', $uploader->streams[1]['headers']['Content-Disposition']);
    $assertSame('0-9/10', $uploader->streams[1]['headers']['Content-Range']);
    $assertSame(['file-bytes'], $uploader->streams[1]['chunks']);
    $assertSame([
        '_type' => 'FILE',
        'fileId' => 902,
    ], $decodeSent($transport, 29)->payload['message']['attaches'][0]);

    $dialog = Chat::fromArray(['id' => 1, 'type' => 'DIALOG', 'status' => 'ACTIVE', 'owner' => 1])
        ->bind($app->api()->messages, $app->api()->chats);
    $assertThrows(RuntimeException::class, static function () use ($dialog): void {
        $dialog->leave();
    });

    $deleteEvent = MessageDeleteEvent::fromArray([
        'chatId' => 500,
        'message' => ['id' => 92, 'chatId' => 500, 'time' => 1092, 'type' => 'USER', 'text' => 'removed'],
    ]);
    Binding::bindApiModel($app, $deleteEvent);
    $eventReflection = new ReflectionClass($deleteEvent);
    $eventActions = $eventReflection->getProperty('actions');
    $eventActions->setAccessible(true);
    $assert($eventActions->getValue($deleteEvent) === $app->api()->messages, 'MessageDeleteEvent must bind to MessageService like PyMax');
    $assertSame(92, $deleteEvent->message->id);
};
