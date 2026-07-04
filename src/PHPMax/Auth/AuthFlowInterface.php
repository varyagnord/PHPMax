<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Api\Auth\AuthService;
use PHPMax\Config\ClientOptions;

interface AuthFlowInterface
{
    public function authenticate(AuthService $authService, ClientOptions $options): AuthResult;
}

