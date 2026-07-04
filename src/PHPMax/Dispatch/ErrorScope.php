<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

final class ErrorScope
{
    public const GLOBAL = 'global';
    public const LOCAL = 'local';

    private function __construct()
    {
    }
}
