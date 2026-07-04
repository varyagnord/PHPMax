<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Domain\SyncState;
use PHPMax\Support\Model;

class WebSyncPayload extends Model
{
    /** @var string|null */
    public $token;
    /** @var int|null */
    public $chatsCount;
    /** @var bool|null */
    public $interactive;
    /** @var int|null */
    public $chatsSync;
    /** @var int|null */
    public $contactsSync;
    /** @var int|null */
    public $presenceSync;
    /** @var int|null */
    public $draftsSync;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
            'chatsCount' => ['type' => 'int', 'default' => 40],
            'interactive' => ['type' => 'bool', 'default' => true],
            'chatsSync' => ['type' => 'int', 'default' => -1],
            'contactsSync' => ['type' => 'int', 'default' => -1],
            'presenceSync' => ['type' => 'int', 'default' => -1],
            'draftsSync' => ['type' => 'int', 'default' => -1],
        ];
    }

    public static function fromSyncState(string $token, SyncState $sync): self
    {
        return new self([
            'token' => $token,
            'chatsSync' => $sync->chatsSync,
            'contactsSync' => $sync->contactsSync,
            'draftsSync' => $sync->draftsSync,
        ]);
    }
}

