<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Api\Messages\MessageService;
use PHPMax\Domain\Attachments\AttachmentFactory;
use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;
use RuntimeException;

class Message extends Model
{
    /** @var int|null */
    public $id;
    /** @var int|null */
    public $chatId;
    /** @var int|null */
    public $sender;
    /** @var string|null */
    public $text;
    /** @var int|null */
    public $time;
    /** @var string|null */
    public $type;
    /** @var int|null */
    public $cid;
    /** @var array<int, mixed> */
    public $attaches = [];
    /** @var array<string, mixed>|null */
    public $stats;
    /** @var string|null */
    public $status;
    /** @var ReactionInfo|null */
    public $reactionInfo;
    /** @var mixed */
    public $options;
    /** @var int|string|null */
    public $prevMessageId;
    /** @var bool|null */
    public $ttl;
    /** @var int|null */
    public $unread;
    /** @var int|null */
    public $mark;
    /** @var list<Element> */
    public $elements = [];
    /** @var MessageService|null */
    private $actions;

    public function bind(MessageService $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function reply(string $text, ?array $attachments = null, bool $notify = true): ?Message
    {
        [$actions, $chatId] = $this->bound();

        return $actions->sendMessage($chatId, $text, $this->requireMessageId(), $attachments, $notify);
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function answer(string $text, ?int $replyTo = null, ?array $attachments = null, bool $notify = true): ?Message
    {
        [$actions, $chatId] = $this->bound();

        return $actions->sendMessage($chatId, $text, $replyTo, $attachments, $notify);
    }

    public function forward(int $chatId, bool $notify = true): ?Message
    {
        [$actions, $sourceChatId] = $this->bound();

        return $actions->forwardMessage($chatId, $this->requireMessageId(), $sourceChatId, $notify);
    }

    public function pin(bool $notifyPin = true): bool
    {
        [$actions, $chatId] = $this->bound();

        return $actions->pinMessage($chatId, $this->requireMessageId(), $notifyPin);
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function edit(string $text, ?array $attachments = null): Message
    {
        [$actions, $chatId] = $this->bound();

        return $actions->editMessage($chatId, $this->requireMessageId(), $text, $attachments);
    }

    public function delete(bool $forMe = false): bool
    {
        [$actions, $chatId] = $this->bound();

        return $actions->deleteMessage($chatId, [$this->requireMessageId()], $forMe);
    }

    public function read(): ReadState
    {
        [$actions, $chatId] = $this->bound();

        return $actions->readMessage($this->requireMessageId(), $chatId);
    }

    public function react(string $reaction): ?ReactionInfo
    {
        [$actions, $chatId] = $this->bound();

        return $actions->addReaction($chatId, (string) $this->requireMessageId(), $reaction);
    }

    public function unreact(): ?ReactionInfo
    {
        [$actions, $chatId] = $this->bound();

        return $actions->removeReaction($chatId, (string) $this->requireMessageId());
    }

    /**
     * @return array<string, ReactionInfo>|null
     */
    public function getReactions(): ?array
    {
        [$actions, $chatId] = $this->bound();
        $messageId = (string) $this->requireMessageId();

        return $actions->getReactions($chatId, [$messageId]);
    }

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'int', 'required' => true],
            'chatId' => ['type' => 'int'],
            'sender' => ['type' => 'int'],
            'text' => ['type' => 'string', 'default' => ''],
            'time' => ['type' => 'int', 'required' => true],
            'type' => ['type' => 'string', 'required' => true],
            'cid' => ['type' => 'int'],
            'attaches' => ['default' => static function (): array {
                return [];
            }, 'factory' => static function ($value): array {
                if (!is_array($value) || !self::isListArray($value)) {
                    throw new ValidationException('Expected attaches list in Message');
                }
                $items = [];
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        throw new ValidationException('Expected attachment item array in Message');
                    }
                    $items[] = AttachmentFactory::fromArray($item);
                }
                return $items;
            }],
            'stats' => ['type' => 'array'],
            'status' => ['type' => 'string'],
            'reactionInfo' => ['type' => ReactionInfo::class],
            'options' => ['type' => 'mixed'],
            'prevMessageId' => ['type' => 'mixed'],
            'ttl' => ['type' => 'bool'],
            'unread' => ['type' => 'int'],
            'mark' => ['type' => 'int'],
            'elements' => ['type' => 'list<' . Element::class . '>', 'default' => static function (): array {
                return [];
            }],
        ];
    }

    protected static function normalizeInput(array $data): array
    {
        if (!isset($data['message']) || !is_array($data['message'])) {
            return $data;
        }

        $message = $data['message'];
        $outerFields = [
            'chatId' => ['chatId', 'chat_id'],
            'prevMessageId' => ['prevMessageId', 'prev_message_id'],
            'ttl' => ['ttl'],
            'unread' => ['unread'],
            'mark' => ['mark'],
        ];

        foreach ($outerFields as $target => $aliases) {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $data) && $data[$alias] !== null) {
                    $message[$target] = $data[$alias];
                    break;
                }
            }
        }

        // MAX периодически кладет полезные поля сообщения во внешнюю оболочку
        // события. Сохраняем отсутствующие во вложенном message поля, чтобы не
        // потерять link пересылки и вложения; при совпадении message важнее.
        foreach ($data as $key => $value) {
            if ($key === 'message' || array_key_exists($key, $message)) {
                continue;
            }
            $message[$key] = $value;
        }

        return $message;
    }

    /**
     * @return array{0: MessageService, 1: int}
     */
    private function bound(): array
    {
        if ($this->actions === null) {
            throw new RuntimeException('Message is not bound to a client.');
        }
        if ($this->chatId === null) {
            throw new RuntimeException('Message does not contain chatId.');
        }

        return [$this->actions, $this->chatId];
    }

    private function requireMessageId(): int
    {
        if ($this->id === null) {
            throw new RuntimeException('Message does not contain id.');
        }

        return $this->id;
    }
}
