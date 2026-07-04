<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

final class ProfileOptions
{
    public const ESIA_VERIFIED_FLAG = 1;
    public const SECOND_FACTOR_PASSWORD_ENABLED = 2;
    public const SECOND_FACTOR_HAS_EMAIL = 3;
    public const SECOND_FACTOR_HAS_HINT = 4;

    private function __construct()
    {
    }
}
