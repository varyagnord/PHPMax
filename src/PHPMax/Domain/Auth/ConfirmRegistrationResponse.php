<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Domain\Profile;
use PHPMax\Support\Model;

class ConfirmRegistrationResponse extends Model
{
    /** @var int|null */
    public $userToken;
    /** @var Profile|null */
    public $profile;
    /** @var string|null */
    public $tokenType;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'userToken' => ['type' => 'int', 'required' => true],
            'profile' => ['type' => Profile::class, 'required' => true],
            'tokenType' => ['type' => 'string', 'required' => true],
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

