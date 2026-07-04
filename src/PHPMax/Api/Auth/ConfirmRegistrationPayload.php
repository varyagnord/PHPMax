<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class ConfirmRegistrationPayload extends Model
{
    /** @var string|null */
    public $firstName;
    /** @var string|null */
    public $lastName;
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $tokenType;

    protected static function schema(): array
    {
        return [
            'firstName' => ['type' => 'string', 'required' => true],
            'lastName' => ['type' => 'string'],
            'token' => ['type' => 'string', 'required' => true],
            'tokenType' => ['type' => 'string', 'default' => AuthType::REGISTER],
        ];
    }
}

