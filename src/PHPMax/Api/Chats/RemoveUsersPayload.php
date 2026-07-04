<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class RemoveUsersPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var list<int> */
    public $userIds = [];
    /** @var string */
    public $operation = ChatMemberOperation::REMOVE;
    /** @var int|null */
    public $cleanMsgPeriod;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'userIds' => ['type' => 'list<int>', 'required' => true],
            'operation' => ['type' => 'string', 'default' => ChatMemberOperation::REMOVE],
            'cleanMsgPeriod' => ['type' => 'int', 'required' => true],
        ];
    }
}
