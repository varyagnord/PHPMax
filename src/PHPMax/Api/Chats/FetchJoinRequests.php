<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class FetchJoinRequests extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var string */
    public $type = 'JOIN_REQUEST';
    /** @var int */
    public $count = 100;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'type' => ['type' => 'string', 'default' => 'JOIN_REQUEST'],
            'count' => ['type' => 'int', 'default' => 100],
        ];
    }
}
