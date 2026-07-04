<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

final class TwoFactorAction
{
    public const SET_PASSWORD = 0;
    public const UPDATE_PASSWORD = 1;
    public const RESTORE_PASSWORD = 2;
    public const HINT = 3;
    public const EMAIL = 4;
    public const REMOVE_2FA = 5;

    private function __construct()
    {
    }
}
