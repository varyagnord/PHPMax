<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class SendMessagePayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var SendMessagePayloadMessage|null */
    public $message;
    /** @var bool|null */
    public $notify;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'message' => ['type' => SendMessagePayloadMessage::class, 'required' => true],
            'notify' => ['type' => 'bool', 'default' => false],
        ];
    }
}

