<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use PHPMax\Support\Model;

class TelemetryEvent extends Model
{
    /** @var int|null */
    public $time;
    /** @var int|null */
    public $userId;
    /** @var string|null */
    public $type;
    /** @var string|null */
    public $event;
    /** @var array<string, mixed> */
    public $params = [];
    /** @var int|null */
    public $sessionId;

    protected static function schema(): array
    {
        return [
            'time' => ['type' => 'int', 'required' => true],
            'userId' => ['type' => 'int', 'required' => true],
            'type' => ['type' => 'string', 'required' => true],
            'event' => ['type' => 'string', 'required' => true],
            'params' => ['type' => 'array', 'default' => static function (): array {
                return [];
            }],
            'sessionId' => ['type' => 'int', 'required' => true],
        ];
    }
}

