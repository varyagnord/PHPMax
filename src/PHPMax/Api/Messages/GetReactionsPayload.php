<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class GetReactionsPayload extends Model
{
    public $chatId;
    public $messageIds = [];

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageIds' => ['type' => 'list<string>', 'required' => true],
        ];
    }
}
