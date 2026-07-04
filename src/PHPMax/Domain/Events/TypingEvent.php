<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Support\Model;

class TypingEvent extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var int|null */
    public $userId;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'userId' => ['type' => 'int', 'required' => true],
        ];
    }
}
