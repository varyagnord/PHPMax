<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class GetMessagesPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var list<int> */
    public $messageIds = [];

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageIds' => ['type' => 'list<int>', 'required' => true],
        ];
    }
}
