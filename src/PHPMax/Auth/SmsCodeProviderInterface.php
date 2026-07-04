<?php

declare(strict_types=1);

namespace PHPMax\Auth;

interface SmsCodeProviderInterface
{
    public function getCode(string $phone): string;
}

