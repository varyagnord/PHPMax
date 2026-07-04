<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use PHPMax\Support\Model;

class TelemetryPayload extends Model
{
    /** @var list<TelemetryEvent> */
    public $events = [];

    protected static function schema(): array
    {
        return [
            'events' => ['type' => 'list<' . TelemetryEvent::class . '>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}

