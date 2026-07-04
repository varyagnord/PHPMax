<?php

declare(strict_types=1);

namespace PHPMax\Auth;

interface EmailCodeProviderInterface
{
    public function getCode(string $email): string;
}
