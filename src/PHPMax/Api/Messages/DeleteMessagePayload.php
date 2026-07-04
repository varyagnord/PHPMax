<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class DeleteMessagePayload extends Model
{
    public $chatId;
    public $messageIds = [];
    public $forMe;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageIds' => ['type' => 'list<int>', 'required' => true],
            'forMe' => ['type' => 'bool', 'default' => false],
        ];
    }
}
