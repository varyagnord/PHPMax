<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class TokenAttrs extends Model
{
    /** @var Token|null */
    public $login;
    /** @var Token|null */
    public $registerToken;

    protected static function schema(): array
    {
        return [
            'login' => ['type' => Token::class, 'payload' => 'LOGIN'],
            'registerToken' => ['type' => Token::class, 'payload' => 'REGISTER'],
        ];
    }
}

