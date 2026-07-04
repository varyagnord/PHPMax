<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class Exp extends Model
{
    /** @var string|null */
    public $chatsCountGroups;

    protected static function schema(): array
    {
        return [
            'chatsCountGroups' => ['type' => 'string', 'default' => "\x0a\x32"],
        ];
    }
}

