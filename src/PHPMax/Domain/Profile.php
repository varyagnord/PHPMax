<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Profile extends Model
{
    /** @var User|null */
    public $contact;
    /** @var array<int|string, mixed>|null */
    public $profileOptions;

    protected static function schema(): array
    {
        return [
            'contact' => ['type' => User::class, 'required' => true],
            'profileOptions' => ['type' => 'array'],
        ];
    }
}
