<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class SendEmailCodePayload extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $verifyCode;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'verifyCode' => ['type' => 'string', 'required' => true],
        ];
    }
}
