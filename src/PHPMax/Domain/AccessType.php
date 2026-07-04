<?php

declare(strict_types=1);

namespace PHPMax\Domain;

final class AccessType
{
    public const PUBLIC = 'PUBLIC';
    public const PRIVATE = 'PRIVATE';
    public const SECRET = 'SECRET';

    private function __construct()
    {
    }
}
