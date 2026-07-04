<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;

class LoginResponse extends Model
{
    /** @var list<Chat> */
    public $chats = [];
    /** @var Profile|null */
    public $profile;
    /** @var array<int|string, list<Message>> */
    public $messages = [];
    /** @var list<User|null> */
    public $contacts = [];
    /** @var string|null */
    public $token;
    /** @var int|null */
    public $time;
    /** @var LoginConfig|null */
    public $config;

    protected static function schema(): array
    {
        return [
            'chats' => ['type' => 'list<' . Chat::class . '>', 'default' => static function (): array {
                return [];
            }],
            'profile' => ['type' => Profile::class, 'required' => true],
            'messages' => ['type' => 'map-list<' . Message::class . '>', 'default' => static function (): array {
                return [];
            }],
            'contacts' => ['default' => static function (): array {
                return [];
            }, 'factory' => static function ($value): array {
                if (!is_array($value) || !self::isListArray($value)) {
                    throw new ValidationException('Expected contacts list in LoginResponse');
                }
                $contacts = [];
                foreach ($value as $item) {
                    if ($item === null) {
                        $contacts[] = null;
                        continue;
                    }
                    if (!is_array($item)) {
                        throw new ValidationException('Expected contact item array or null in LoginResponse');
                    }
                    $contacts[] = User::fromArray($item);
                }
                return $contacts;
            }],
            'token' => ['type' => 'string'],
            'time' => ['type' => 'int'],
            'config' => ['type' => LoginConfig::class],
        ];
    }

    public function updateSyncState(SyncState $current): SyncState
    {
        $syncTime = $this->time;
        $configHash = $this->config !== null ? $this->config->hash : null;

        return new SyncState([
            'chatsSync' => $syncTime !== null ? $syncTime : $current->chatsSync,
            'contactsSync' => $syncTime !== null ? $syncTime : $current->contactsSync,
            'draftsSync' => $syncTime !== null ? $syncTime : $current->draftsSync,
            'presenceSync' => $syncTime !== null ? $syncTime : $current->presenceSync,
            'configHash' => $configHash !== null ? $configHash : $current->configHash,
        ]);
    }
}
