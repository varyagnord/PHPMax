<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class SyncState extends Model
{
    public const DEFAULT_CONFIG_HASH = '00000000-0000000000000000-00000000-0000000000000000-0000000000000000-0-0000000000000000-00000000';

    /** @var int|null */
    public $chatsSync;
    /** @var int|null */
    public $contactsSync;
    /** @var int|null */
    public $draftsSync;
    /** @var int|null */
    public $presenceSync;
    /** @var int|string|null */
    public $configHash;

    protected static function schema(): array
    {
        return [
            'chatsSync' => ['type' => 'int', 'payload' => 'chats_sync', 'default' => -1],
            'contactsSync' => ['type' => 'int', 'payload' => 'contacts_sync', 'default' => -1],
            'draftsSync' => ['type' => 'int', 'payload' => 'drafts_sync', 'default' => -1],
            'presenceSync' => ['type' => 'int', 'payload' => 'presence_sync', 'default' => -1],
            'configHash' => ['type' => 'mixed', 'payload' => 'config_hash', 'default' => self::DEFAULT_CONFIG_HASH],
        ];
    }
}
