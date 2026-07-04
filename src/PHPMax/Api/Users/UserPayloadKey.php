<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

final class UserPayloadKey
{
    public const CONTACT = 'contact';
    public const CONTACTS = 'contacts';
    public const SESSIONS = 'sessions';

    private function __construct()
    {
    }
}
