<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ForwardMessagePayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var ForwardMessagePayloadMessage|null */
    public $message;
    /** @var bool|null */
    public $notify;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'message' => ['type' => ForwardMessagePayloadMessage::class, 'required' => true],
            'notify' => ['type' => 'bool', 'default' => true],
        ];
    }
}

