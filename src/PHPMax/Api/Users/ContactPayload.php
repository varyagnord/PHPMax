<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Support\Model;

class ContactPayload extends Model
{
    /** @var string|null */
    public $firstName;

    protected static function schema(): array
    {
        return [
            'firstName' => ['type' => 'string', 'required' => true],
        ];
    }
}
