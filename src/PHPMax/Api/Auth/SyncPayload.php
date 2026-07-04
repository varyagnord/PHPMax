<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Domain\SyncState;
use PHPMax\Support\Model;

class SyncPayload extends Model
{
    /** @var MobileUserAgentPayload|null */
    public $userAgent;
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $chatHashFingerprint;
    /** @var int|null */
    public $chatsCount;
    /** @var int|null */
    public $chatsSync;
    /** @var int|null */
    public $contactsSync;
    /** @var int|null */
    public $draftsSync;
    /** @var bool|null */
    public $interactive;
    /** @var int|null */
    public $presenceSync;
    /** @var Exp|null */
    public $exp;
    /** @var int|string|null */
    public $configHash;

    protected static function schema(): array
    {
        return [
            'userAgent' => ['type' => MobileUserAgentPayload::class, 'required' => true],
            'token' => ['type' => 'string', 'required' => true],
            'chatHashFingerprint' => ['type' => 'string'],
            'chatsCount' => ['type' => 'int'],
            'chatsSync' => ['type' => 'int', 'default' => -1],
            'contactsSync' => ['type' => 'int', 'default' => -1],
            'draftsSync' => ['type' => 'int', 'default' => -1],
            'interactive' => ['type' => 'bool', 'default' => true],
            'presenceSync' => ['type' => 'int', 'default' => -1],
            'exp' => ['type' => Exp::class, 'default' => static function (): Exp {
                return new Exp();
            }],
            'configHash' => ['type' => 'mixed', 'default' => SyncState::DEFAULT_CONFIG_HASH],
        ];
    }

    public static function fromSyncState(MobileUserAgentPayload $userAgent, string $token, SyncState $sync): self
    {
        return new self([
            'userAgent' => $userAgent,
            'token' => $token,
            'chatsSync' => $sync->chatsSync,
            'contactsSync' => $sync->contactsSync,
            'draftsSync' => $sync->draftsSync,
            'presenceSync' => $sync->presenceSync,
            'configHash' => $sync->configHash,
        ]);
    }
}

