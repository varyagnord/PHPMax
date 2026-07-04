<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

final class Screen
{
    public const BACKGROUND = 1;
    public const CONTACTS = 100;
    public const CHATS = 150;
    public const SEARCH = 151;
    public const CALLS = 300;
    public const CHAT = 350;
    public const SETTINGS = 450;
    public const MINIAPP = 500;

    private function __construct()
    {
    }
}
