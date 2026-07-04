<?php

declare(strict_types=1);

namespace PHPMax\Api\Session;

use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;

class SessionService
{
    /** @var App */
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function handshake(string $mtInstanceId, MobileUserAgentPayload $userAgent, string $deviceId): void
    {
        if ($userAgent->deviceType === DeviceType::WEB) {
            $this->webHandshake($userAgent, $deviceId);
            return;
        }

        $this->mobileHandshake($mtInstanceId, $userAgent, $deviceId);
    }

    public function mobileHandshake(string $mtInstanceId, MobileUserAgentPayload $userAgent, string $deviceId): void
    {
        $payload = new MobileHandshakePayload([
            'mtInstanceId' => $mtInstanceId,
            'userAgent' => $userAgent,
            'clientSessionId' => $this->app->options()->clientSessionId,
            'deviceId' => $deviceId,
        ]);

        $this->app->invoke(Opcode::SESSION_INIT, $payload->toArray());
    }

    public function webHandshake(MobileUserAgentPayload $userAgent, string $deviceId): void
    {
        $payload = new WebHandshakePayload([
            'userAgent' => $userAgent,
            'deviceId' => $deviceId,
        ]);

        $this->app->invoke(Opcode::SESSION_INIT, $payload->toArray());
    }
}

