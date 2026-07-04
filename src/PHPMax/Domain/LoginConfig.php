<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class LoginConfig extends Model
{
    /** @var int|string|null */
    public $hash;

    protected static function schema(): array
    {
        return [
            'hash' => ['type' => 'mixed'],
        ];
    }
}

