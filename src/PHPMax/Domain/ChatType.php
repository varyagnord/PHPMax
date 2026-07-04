<?php

declare(strict_types=1);

namespace PHPMax\Domain;

final class ChatType
{
    public const DIALOG = 'DIALOG';
    public const CHAT = 'CHAT';
    public const CHANNEL = 'CHANNEL';

    private function __construct()
    {
    }
}

