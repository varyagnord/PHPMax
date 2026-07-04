<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class Token extends Model
{
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

