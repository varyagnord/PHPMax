<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ForwardLink extends Model
{
    /** @var string|null */
    public $type;
    /** @var string|null */
    public $messageId;
    /** @var int|null */
    public $chatId;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'default' => 'FORWARD'],
            'messageId' => ['type' => 'string', 'required' => true],
            'chatId' => ['type' => 'int', 'required' => true],
        ];
    }
}

