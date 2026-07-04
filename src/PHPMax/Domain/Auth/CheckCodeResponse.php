<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class CheckCodeResponse extends Model
{
    /** @var TokenAttrs|null */
    public $tokenAttrs;
    /** @var PasswordChallenge|null */
    public $passwordChallenge;

    protected static function schema(): array
    {
        return [
            'tokenAttrs' => ['type' => TokenAttrs::class, 'default' => static function (): TokenAttrs {
                return new TokenAttrs();
            }],
            'passwordChallenge' => ['type' => PasswordChallenge::class],
        ];
    }

    public function loginToken(): ?string
    {
        return $this->tokenAttrs !== null && $this->tokenAttrs->login !== null
            ? $this->tokenAttrs->login->token
            : null;
    }

    public function registerToken(): ?string
    {
        return $this->tokenAttrs !== null && $this->tokenAttrs->registerToken !== null
            ? $this->tokenAttrs->registerToken->token
            : null;
    }
}

