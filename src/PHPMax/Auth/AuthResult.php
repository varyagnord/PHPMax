<?php

declare(strict_types=1);

namespace PHPMax\Auth;

class AuthResult
{
    /** @var string|null */
    public $token;

    public function __construct(?string $token)
    {
        $this->token = $token;
    }
}

