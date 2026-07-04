<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Member extends Model
{
    /** @var User|null */
    public $contact;
    /** @var Presence|null */
    public $presence;

    protected static function schema(): array
    {
        return [
            'contact' => ['type' => User::class, 'required' => true],
            'presence' => ['type' => Presence::class, 'required' => true],
        ];
    }
}
