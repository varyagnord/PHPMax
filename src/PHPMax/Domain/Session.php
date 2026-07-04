<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Session extends Model
{
    /** @var int|string|null */
    public $id;
    /** @var string|null */
    public $deviceId;
    /** @var bool|null */
    public $current;
    /** @var string|null */
    public $userAgent;
    /** @var string|null */
    public $appVersion;
    /** @var string|null */
    public $deviceName;
    /** @var string|null */
    public $deviceType;
    /** @var string|null */
    public $platform;
    /** @var string|null */
    public $ip;
    /** @var string|null */
    public $location;
    /** @var int|null */
    public $created;
    /** @var int|null */
    public $updated;
    /** @var int|null */
    public $lastActivity;
    /** @var mixed */
    public $options;

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'mixed'],
            'deviceId' => ['type' => 'string'],
            'current' => ['type' => 'bool'],
            'userAgent' => ['type' => 'string'],
            'appVersion' => ['type' => 'string'],
            'deviceName' => ['type' => 'string'],
            'deviceType' => ['type' => 'string'],
            'platform' => ['type' => 'string'],
            'ip' => ['type' => 'string'],
            'location' => ['type' => 'string'],
            'created' => ['type' => 'int'],
            'updated' => ['type' => 'int'],
            'lastActivity' => ['type' => 'int'],
            'options' => ['type' => 'mixed'],
        ];
    }
}
