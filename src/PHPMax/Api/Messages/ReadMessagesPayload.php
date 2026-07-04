<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ReadMessagesPayload extends Model
{
    public $type;
    public $chatId;
    public $messageId;
    public $mark;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'required' => true],
            'chatId' => ['type' => 'int', 'required' => true],
            'messageId' => ['type' => 'mixed', 'required' => true],
            'mark' => ['type' => 'int', 'required' => true],
        ];
    }
}

