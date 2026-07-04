<?php

declare(strict_types=1);

namespace PHPMax\Domain;

final class MessageStatus
{
    public const EDITED = 'EDITED';
    public const REMOVED = 'REMOVED';

    private function __construct()
    {
    }
}
