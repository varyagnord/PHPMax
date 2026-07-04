<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class PasswordChallenge extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $hint;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'hint' => ['type' => 'string'],
        ];
    }
}

