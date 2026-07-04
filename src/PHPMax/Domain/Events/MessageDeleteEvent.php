<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Api\Messages\MessageService;
use PHPMax\Domain\Chat;
use PHPMax\Domain\Message;
use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;

class MessageDeleteEvent extends Model
{
    /** @var list<int> */
    public $messageIds = [];
    /** @var int|null */
    public $chatId;
    /** @var Chat|null */
    public $chat;
    /** @var Message|null */
    public $message;
    /** @var bool */
    public $ttl = false;
    /** @var MessageService|null */
    private $actions;

    public function bind(MessageService $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    protected static function schema(): array
    {
        return [
            'messageIds' => ['type' => 'array', 'required' => true, 'factory' => static function ($value): array {
                if (!is_array($value) || !self::isListArray($value)) {
                    throw new ValidationException('Expected messageIds list in MessageDeleteEvent');
                }
                $messageIds = [];
                foreach ($value as $item) {
                    if (!is_int($item) && !is_string($item)) {
                        throw new ValidationException('Expected scalar message id in MessageDeleteEvent');
                    }
                    $messageIds[] = (int) $item;
                }

                return $messageIds;
            }],
            'chatId' => ['type' => 'int', 'required' => true],
            'chat' => ['type' => Chat::class],
            'message' => ['type' => Message::class],
            'ttl' => ['type' => 'bool', 'default' => false],
        ];
    }

    protected static function normalizeInput(array $data): array
    {
        if (isset($data['chat']) && is_array($data['chat'])) {
            $messageIds = $data['messageIds'] ?? $data['message_ids'] ?? null;
            $chatId = $data['chat']['id'] ?? null;
            if ($chatId !== null && $messageIds !== null) {
                return [
                    'chat' => $data['chat'],
                    'ttl' => $data['ttl'] ?? false,
                    'messageIds' => $messageIds,
                    'chatId' => $chatId,
                ];
            }
        }

        if (isset($data['message']) && is_array($data['message'])) {
            $messageId = $data['message']['id'] ?? null;
            $chatId = $data['chatId'] ?? $data['chat_id'] ?? null;
            if ($chatId !== null && $messageId !== null) {
                return [
                    'chatId' => $chatId,
                    'message' => $data['message'],
                    'ttl' => $data['ttl'] ?? false,
                    'messageIds' => [$messageId],
                ];
            }
        }

        return $data;
    }
}
