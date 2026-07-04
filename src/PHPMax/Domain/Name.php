<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Name extends Model
{
    /** @var string|null */
    public $name;
    /** @var string|null */
    public $firstName;
    /** @var string|null */
    public $lastName;
    /** @var string|null */
    public $type;

    protected static function schema(): array
    {
        return [
            'name' => ['type' => 'string'],
            'firstName' => ['type' => 'string'],
            'lastName' => ['type' => 'string'],
            'type' => ['type' => 'string'],
        ];
    }
}
