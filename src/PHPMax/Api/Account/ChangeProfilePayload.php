<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class ChangeProfilePayload extends Model
{
    /** @var string|null */
    public $firstName;
    /** @var string|null */
    public $lastName;
    /** @var string|null */
    public $description;
    /** @var string|null */
    public $photoToken;
    /** @var string */
    public $avatarType = AvatarType::USER_AVATAR;

    protected static function schema(): array
    {
        return [
            'firstName' => ['type' => 'string', 'required' => true],
            'lastName' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'photoToken' => ['type' => 'string'],
            'avatarType' => ['type' => 'string', 'default' => AvatarType::USER_AVATAR],
        ];
    }
}
