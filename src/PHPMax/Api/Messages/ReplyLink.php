<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ReplyLink extends Model
{
    /** @var string|null */
    public $type;
    /** @var int|null */
    public $messageId;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'default' => 'REPLY'],
            'messageId' => ['type' => 'int', 'required' => true],
        ];
    }
}

