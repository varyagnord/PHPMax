<?php

declare(strict_types=1);

namespace PHPMax\Session;

use PHPMax\Domain\SyncState;
use PHPMax\Support\Model;

class SessionInfo extends Model
{
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $deviceId;
    /** @var string|null */
    public $phone;
    /** @var string|null */
    public $mtInstanceId;
    /** @var SyncState|null */
    public $sync;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
            'deviceId' => ['type' => 'string', 'required' => true],
            'phone' => ['type' => 'string', 'required' => true],
            'mtInstanceId' => ['type' => 'string', 'default' => ''],
            'sync' => ['type' => SyncState::class, 'default' => static function (): SyncState {
                return new SyncState();
            }],
        ];
    }
}

