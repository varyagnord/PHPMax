<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class CreateGroupPayload extends Model
{
    /** @var CreateGroupMessage|null */
    public $message;
    /** @var bool */
    public $notify = true;

    protected static function schema(): array
    {
        return [
            'message' => ['type' => CreateGroupMessage::class, 'required' => true],
            'notify' => ['type' => 'bool', 'default' => true],
        ];
    }
}
