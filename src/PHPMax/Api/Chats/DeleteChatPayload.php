<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class DeleteChatPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var int|null */
    public $lastEventTime;
    /** @var bool */
    public $forAll = true;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'lastEventTime' => ['type' => 'int', 'required' => true],
            'forAll' => ['type' => 'bool', 'default' => true],
        ];
    }
}
