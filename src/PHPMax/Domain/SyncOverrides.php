<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class SyncOverrides extends Model
{
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
            'chatsSync' => ['type' => 'int', 'payload' => 'chats_sync'],
            'contactsSync' => ['type' => 'int', 'payload' => 'contacts_sync'],
            'draftsSync' => ['type' => 'int', 'payload' => 'drafts_sync'],
            'presenceSync' => ['type' => 'int', 'payload' => 'presence_sync'],
            'configHash' => ['type' => 'mixed', 'payload' => 'config_hash'],
        ];
    }

    public function resolve(SyncState $saved): SyncState
    {
        return new SyncState([
            'chatsSync' => $this->chatsSync !== null ? $this->chatsSync : $saved->chatsSync,
            'contactsSync' => $this->contactsSync !== null ? $this->contactsSync : $saved->contactsSync,
            'draftsSync' => $this->draftsSync !== null ? $this->draftsSync : $saved->draftsSync,
            'presenceSync' => $this->presenceSync !== null ? $this->presenceSync : $saved->presenceSync,
            'configHash' => $this->configHash !== null ? $this->configHash : $saved->configHash,
        ]);
    }
}
