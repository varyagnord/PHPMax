<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class RemoveReactionPayload extends Model
{
    public $chatId;
    public $messageId;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageId' => ['type' => 'string', 'required' => true],
        ];
    }
}

