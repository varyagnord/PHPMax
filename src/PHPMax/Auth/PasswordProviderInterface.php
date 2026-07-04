<?php

declare(strict_types=1);

namespace PHPMax\Auth;

interface PasswordProviderInterface
{
    public function getPassword(?string $hint = null): string;
}

