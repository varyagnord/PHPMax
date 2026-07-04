<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Api\Binding;
use PHPMax\Domain\FileRequest;
use PHPMax\Domain\Message;
use PHPMax\Domain\ReactionInfo;
use PHPMax\Domain\ReadState;
use PHPMax\Domain\VideoRequest;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Formatting\Formatter;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use PHPMax\Support\Model;

class MessageService
{
    /** @var App */
    private $app;
    /** @var int */
    private $prevCid;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->prevCid = (int) floor(microtime(true) * 1000);
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function sendMessage(int $chatId, string $text, ?int $replyTo = null, ?array $attachments = null, bool $notify = true): ?Message
    {
        [$cleanText, $elements] = Formatter::formatMarkdown($text);
        $payload = new SendMessagePayload([
            'chatId' => $chatId,
            'message' => new SendMessagePayloadMessage([
                'text' => $cleanText,
                'cid' => $this->nextCid(),
                'elements' => $elements,
                'attaches' => $this->normalizeAttachments($attachments),
                'link' => $replyTo !== null ? new ReplyLink(['messageId' => $replyTo]) : null,
            ]),
            'notify' => $notify,
        ]);

        $response = $this->app->invoke(Opcode::MSG_SEND, $payload->toArray());

        return Binding::bindApiModel($this->app, Message::fromArray($this->requireResponsePayload($response->payload)));
    }

    public function forwardMessage(int $chatId, $messageId, ?int $sourceChatId = null, bool $notify = true): ?Message
    {
        $sourceChatId = $sourceChatId !== null ? $sourceChatId : $chatId;
        $payload = new ForwardMessagePayload([
            'chatId' => $chatId,
            'message' => new ForwardMessagePayloadMessage([
                'cid' => -$this->nextCid(),
                'link' => new ForwardLink([
                    'messageId' => (string) $messageId,
                    'chatId' => $sourceChatId,
                ]),
            ]),
            'notify' => $notify,
        ]);

        $response = $this->app->invoke(Opcode::MSG_SEND, $payload->toArray());

        return Binding::bindApiModel($this->app, Message::fromArray($this->requireResponsePayload($response->payload)));
    }

    /**
     * @param list<int> $messageIds
     * @return list<Message>
     */
    public function getMessages(int $chatId, array $messageIds): array
    {
        $payload = new GetMessagesPayload([
            'chatId' => $chatId,
            'messageIds' => $messageIds,
        ]);
        $response = $this->app->invoke(Opcode::MSG_GET, $payload->toArray());

        $messages = [];
        foreach ($this->parseMessageList($response->payload) as $item) {
            if (!isset($item['chatId'])) {
                $item['chatId'] = $chatId;
            }
            $messages[] = Binding::bindApiModel($this->app, Message::fromArray($item));
        }

        return $messages;
    }

    public function getMessage(int $chatId, int $messageId): ?Message
    {
        $messages = $this->getMessages($chatId, [$messageId]);

        return $messages[0] ?? null;
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function editMessage(int $chatId, int $messageId, string $text, ?array $attachments = null): Message
    {
        [$cleanText, $elements] = Formatter::formatMarkdown($text);
        $payload = new EditMessagePayload([
            'chatId' => $chatId,
            'messageId' => $messageId,
            'text' => $cleanText,
            'elements' => $elements,
            'attachments' => $this->normalizeAttachments($attachments),
        ]);
        $response = $this->app->invoke(Opcode::MSG_EDIT, $payload->toArray());
        $message = $this->requireResponsePayloadItem($response->payload, MessagePayloadKey::MESSAGE);
        if (!isset($message['chatId'])) {
            $message['chatId'] = $chatId;
        }

        return Binding::bindApiModel($this->app, Message::fromArray($message));
    }

    /**
     * @return list<Message>|null
     */
    public function fetchHistory(
        int $chatId,
        int $forward = 0,
        int $backward = 40,
        int $backwardTime = 0,
        int $forwardTime = 0,
        ?int $from = null,
        string $itemType = ItemType::REGULAR,
        bool $getChat = false,
        bool $getMessages = true,
        bool $interactive = false
    ): ?array {
        $payload = new ChatHistoryPayload([
            'chatId' => $chatId,
            'forward' => $forward,
            'backward' => $backward,
            'backwardTime' => $backwardTime,
            'forwardTime' => $forwardTime,
            'from' => $from !== null ? $from : (int) floor(microtime(true) * 1000),
            'itemType' => $itemType,
            'getChat' => $getChat,
            'getMessages' => $getMessages,
            'interactive' => $interactive,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_HISTORY, $payload->toArray());
        $items = $this->parseMessageList($response->payload);
        if ($items === []) {
            return null;
        }
        $messages = [];
        foreach ($items as $item) {
            $messages[] = Binding::bindApiModel($this->app, Message::fromArray($item));
        }

        return $messages !== [] ? $messages : null;
    }

    /**
     * @param list<int> $messageIds
     */
    public function deleteMessage(int $chatId, array $messageIds, bool $forMe): bool
    {
        $payload = new DeleteMessagePayload([
            'chatId' => $chatId,
            'messageIds' => $messageIds,
            'forMe' => $forMe,
        ]);
        $this->app->invoke(Opcode::MSG_DELETE, $payload->toArray());

        return true;
    }

    public function pinMessage(int $chatId, int $messageId, bool $notifyPin): bool
    {
        $payload = new PinMessagePayload([
            'chatId' => $chatId,
            'notifyPin' => $notifyPin,
            'pinMessageId' => $messageId,
        ]);
        $this->app->invoke(Opcode::CHAT_UPDATE, $payload->toArray());

        return true;
    }

    public function getVideoById(int $chatId, $messageId, int $videoId): ?VideoRequest
    {
        $payload = new GetVideoPayload([
            'chatId' => $chatId,
            'messageId' => $messageId,
            'videoId' => $videoId,
        ]);
        $response = $this->app->invoke(Opcode::VIDEO_PLAY, $payload->toArray());
        if ($response->payload === null || $response->payload === []) {
            return null;
        }

        return VideoRequest::fromArray($response->payload);
    }

    public function getFileById(int $chatId, $messageId, int $fileId): ?FileRequest
    {
        $payload = new GetFilePayload([
            'chatId' => $chatId,
            'messageId' => $messageId,
            'fileId' => $fileId,
        ]);
        $response = $this->app->invoke(Opcode::FILE_DOWNLOAD, $payload->toArray());
        if ($response->payload === null || $response->payload === []) {
            return null;
        }

        return FileRequest::fromArray($response->payload);
    }

    public function addReaction(int $chatId, string $messageId, string $reaction): ?ReactionInfo
    {
        $payload = new AddReactionPayload([
            'chatId' => $chatId,
            'messageId' => $messageId,
            'reaction' => new ReactionInfoPayload(['id' => $reaction]),
        ]);
        $response = $this->app->invoke(Opcode::MSG_REACTION, $payload->toArray());

        return $this->parseOptionalReactionInfo($response->payload);
    }

    /**
     * @param list<string> $messageIds
     * @return array<string, ReactionInfo>|null
     */
    public function getReactions(int $chatId, array $messageIds): ?array
    {
        $payload = new GetReactionsPayload([
            'chatId' => $chatId,
            'messageIds' => $messageIds,
        ]);
        $response = $this->app->invoke(Opcode::MSG_GET_REACTIONS, $payload->toArray());
        $items = $this->parseReactionInfoMap($response->payload);
        if ($items === null) {
            return null;
        }
        $result = [];
        foreach ($items as $messageId => $data) {
            $result[(string) $messageId] = ReactionInfo::fromArray($data);
        }

        return $result;
    }

    public function removeReaction(int $chatId, string $messageId): ?ReactionInfo
    {
        $payload = new RemoveReactionPayload([
            'chatId' => $chatId,
            'messageId' => $messageId,
        ]);
        $response = $this->app->invoke(Opcode::MSG_CANCEL_REACTION, $payload->toArray());

        return $this->parseOptionalReactionInfo($response->payload);
    }

    public function readMessage($messageId, int $chatId): ReadState
    {
        $payload = new ReadMessagesPayload([
            'type' => ReadAction::READ_MESSAGE,
            'chatId' => $chatId,
            'messageId' => $messageId,
            'mark' => (int) floor(microtime(true) * 1000),
        ]);
        $response = $this->app->invoke(Opcode::CHAT_MARK, $payload->toArray());

        return ReadState::fromArray($this->requireResponsePayload($response->payload));
    }

    private function nextCid(): int
    {
        $now = (int) floor(microtime(true) * 1000);
        $next = max($now, $this->prevCid + 1);
        $this->prevCid = $next;

        return $next;
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireResponsePayload(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            throw new PHPMaxException('Missing payload in response');
        }

        return $payload;
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireResponsePayloadItem(?array $payload, string $key): array
    {
        $payload = $this->requireResponsePayload($payload);
        $item = $payload[$key] ?? null;
        if (!is_array($item) || $item === []) {
            throw new PHPMaxException('Missing payload item in response: ' . $key);
        }

        return $item;
    }

    /**
     * @param array<mixed>|null $payload
     * @return list<array<mixed>>
     */
    private function parseMessageList(?array $payload): array
    {
        $items = $payload[MessagePayloadKey::MESSAGES] ?? null;
        if ($items === null || $items === []) {
            return [];
        }
        if (!is_array($items) || !$this->isList($items)) {
            throw new PHPMaxException('Invalid messages list in response');
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new PHPMaxException('Invalid message item in response');
            }
            $result[] = $item;
        }

        return $result;
    }

    private function parseOptionalReactionInfo(?array $payload): ?ReactionInfo
    {
        $info = $payload[MessagePayloadKey::REACTION_INFO] ?? null;
        if ($info === null || $info === []) {
            return null;
        }
        if (!is_array($info)) {
            throw new PHPMaxException('Invalid reactionInfo in response');
        }

        return ReactionInfo::fromArray($info);
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<string, array<mixed>>|null
     */
    private function parseReactionInfoMap(?array $payload): ?array
    {
        $items = $payload[MessagePayloadKey::MESSAGES_REACTIONS] ?? null;
        if ($items === null) {
            return null;
        }
        if (!is_array($items)) {
            throw new PHPMaxException('Invalid messagesReactions in response');
        }
        if ($items !== [] && $this->isList($items)) {
            throw new PHPMaxException('Invalid messagesReactions map in response');
        }

        $result = [];
        foreach ($items as $messageId => $data) {
            if (!is_array($data)) {
                throw new PHPMaxException('Invalid reaction info item in response');
            }
            $result[(string) $messageId] = $data;
        }

        return $result;
    }

    /**
     * @param array<mixed> $payload
     */
    private function isList(array $payload): bool
    {
        $expected = 0;
        foreach (array_keys($payload) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    /**
     * @param array<int, mixed>|null $attachments
     * @return list<array<mixed>>
     */
    private function normalizeAttachments(?array $attachments): array
    {
        if ($attachments === null) {
            return [];
        }
        $result = [];
        foreach ($attachments as $attachment) {
            if ($attachment instanceof Photo) {
                $result[] = $this->app->api()->uploads->uploadPhoto($attachment)->toArray();
            } elseif ($attachment instanceof Video) {
                $result[] = $this->app->api()->uploads->uploadVideo($attachment)->toArray();
            } elseif ($attachment instanceof File) {
                $result[] = $this->app->api()->uploads->uploadFile($attachment)->toArray();
            } elseif ($attachment instanceof Model) {
                $result[] = $attachment->toArray();
            } elseif (is_array($attachment)) {
                $result[] = $attachment;
            }
        }

        return $result;
    }
}
