<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class LeaveChatPayload extends Model
{
    /** @var int|null */
    public $chatId;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
        ];
    }
}
