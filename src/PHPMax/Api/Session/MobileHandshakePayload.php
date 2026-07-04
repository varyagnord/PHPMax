<?php

declare(strict_types=1);

namespace PHPMax\Api\Session;

use PHPMax\Support\Model;

class MobileHandshakePayload extends Model
{
    /** @var string|null */
    public $mtInstanceId;
    /** @var MobileUserAgentPayload|null */
    public $userAgent;
    /** @var int|null */
    public $clientSessionId;
    /** @var string|null */
    public $deviceId;

    protected static function schema(): array
    {
        return [
            'mtInstanceId' => ['type' => 'string', 'payload' => 'mt_instanceid', 'required' => true],
            'userAgent' => ['type' => MobileUserAgentPayload::class, 'required' => true],
            'clientSessionId' => ['type' => 'int', 'default' => 1],
            'deviceId' => ['type' => 'string', 'required' => true],
        ];
    }
}

