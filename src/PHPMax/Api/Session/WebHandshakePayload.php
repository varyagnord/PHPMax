<?php

declare(strict_types=1);

namespace PHPMax\Api\Session;

use PHPMax\Support\Model;

class WebHandshakePayload extends Model
{
    /** @var MobileUserAgentPayload|null */
    public $userAgent;
    /** @var string|null */
    public $deviceId;

    protected static function schema(): array
    {
        return [
            'userAgent' => ['type' => MobileUserAgentPayload::class, 'required' => true],
            'deviceId' => ['type' => 'string', 'required' => true],
        ];
    }

    public function toArray(bool $excludeNull = true): array
    {
        return [
            'userAgent' => $this->userAgent instanceof MobileUserAgentPayload
                ? $this->userAgent->toWebPayload()
                : [],
            'deviceId' => $this->deviceId,
        ];
    }
}

