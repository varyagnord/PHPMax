<?php

declare(strict_types=1);

namespace PHPMax\Protocol;

final class Command
{
    public const REQUEST = 0;
    public const RESPONSE = 1;
    public const EVENT = 2;
    public const ERROR = 3;

    private function __construct()
    {
    }
}

