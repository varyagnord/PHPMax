<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class PinMessagePayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var bool|null */
    public $notifyPin;
    /** @var int|null */
    public $pinMessageId;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'notifyPin' => ['type' => 'bool', 'required' => true],
            'pinMessageId' => ['type' => 'int', 'required' => true],
        ];
    }
}
