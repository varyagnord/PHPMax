<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class RequestEmailCodePayload extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $email;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true],
        ];
    }
}
