<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

final class AuthType
{
    public const START_AUTH = 'START_AUTH';
    public const CHECK_CODE = 'CHECK_CODE';
    public const REGISTER = 'REGISTER';
    public const RESEND = 'RESEND';

    private function __construct()
    {
    }
}

