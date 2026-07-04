<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class ContactInfo extends Model
{
    /** @var string|null */
    public $phone;
    /** @var string|null */
    public $firstName;
    /** @var string|null */
    public $lastName;

    protected static function schema(): array
    {
        return [
            'phone' => ['type' => 'string', 'required' => true],
            'firstName' => ['type' => 'string', 'required' => true],
            'lastName' => ['type' => 'string'],
        ];
    }
}
