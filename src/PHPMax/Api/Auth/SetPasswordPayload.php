<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class SetPasswordPayload extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $password;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'password' => ['type' => 'string', 'required' => true],
        ];
    }
}
