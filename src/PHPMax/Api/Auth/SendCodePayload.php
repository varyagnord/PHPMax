<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class SendCodePayload extends Model
{
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $verifyCode;
    /** @var string|null */
    public $authTokenType;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
            'verifyCode' => ['type' => 'string', 'required' => true],
            'authTokenType' => ['type' => 'string', 'default' => AuthType::CHECK_CODE],
        ];
    }
}

