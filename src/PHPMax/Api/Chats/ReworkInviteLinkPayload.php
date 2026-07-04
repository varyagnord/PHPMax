<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class ReworkInviteLinkPayload extends Model
{
    /** @var bool */
    public $revokePrivateLink = true;
    /** @var int|null */
    public $chatId;

    protected static function schema(): array
    {
        return [
            'revokePrivateLink' => ['type' => 'bool', 'default' => true],
            'chatId' => ['type' => 'int', 'required' => true],
        ];
    }
}
