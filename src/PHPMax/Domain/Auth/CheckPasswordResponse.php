<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class CheckPasswordResponse extends Model
{
    /** @var TokenAttrs|null */
    public $tokenAttrs;
    /** @var string|null */
    public $error;

    protected static function schema(): array
    {
        return [
            'tokenAttrs' => ['type' => TokenAttrs::class, 'default' => static function (): TokenAttrs {
                return new TokenAttrs();
            }],
            'error' => ['type' => 'string'],
        ];
    }

    public function loginToken(): ?string
    {
        return $this->tokenAttrs !== null && $this->tokenAttrs->login !== null
            ? $this->tokenAttrs->login->token
            : null;
    }
}

