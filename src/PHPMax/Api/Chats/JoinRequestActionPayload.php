<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class JoinRequestActionPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var list<int> */
    public $userIds = [];
    /** @var string */
    public $type = 'JOIN_REQUEST';
    /** @var bool|null */
    public $showHistory;
    /** @var string|null */
    public $operation;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'userIds' => ['type' => 'list<int>', 'required' => true],
            'type' => ['type' => 'string', 'default' => 'JOIN_REQUEST'],
            'showHistory' => ['type' => 'bool'],
            'operation' => ['type' => 'string', 'required' => true],
        ];
    }
}
