<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

final class ReadAction
{
    public const READ_MESSAGE = 'READ_MESSAGE';
    public const READ_REACTION = 'READ_REACTION';

    private function __construct()
    {
    }
}

