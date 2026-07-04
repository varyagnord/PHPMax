<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class InviteUsersPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var list<int> */
    public $userIds = [];
    /** @var bool|null */
    public $showHistory;
    /** @var string */
    public $operation = ChatMemberOperation::ADD;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'userIds' => ['type' => 'list<int>', 'required' => true],
            'showHistory' => ['type' => 'bool', 'required' => true],
            'operation' => ['type' => 'string', 'default' => ChatMemberOperation::ADD],
        ];
    }
}
