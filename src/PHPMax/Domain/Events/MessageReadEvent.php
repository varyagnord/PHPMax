<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Support\Model;

class MessageReadEvent extends Model
{
    /** @var bool|null */
    public $setAsUnread;
    /** @var int|null */
    public $chatId;
    /** @var int|null */
    public $userId;
    /** @var int|null */
    public $mark;

    protected static function schema(): array
    {
        return [
            'setAsUnread' => ['type' => 'bool', 'required' => true],
            'chatId' => ['type' => 'int', 'required' => true],
            'userId' => ['type' => 'int', 'required' => true],
            'mark' => ['type' => 'int', 'required' => true],
        ];
    }
}
