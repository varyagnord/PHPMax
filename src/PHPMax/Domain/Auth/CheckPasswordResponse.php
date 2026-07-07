<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class CheckPasswordResponse extends Model
{
    /** @var TokenAttrs|null */
    public $tokenAttrs;
    /** @var mixed */
    public $error;

    protected static function schema(): array
    {
        return [
            'tokenAttrs' => ['type' => TokenAttrs::class, 'default' => static function (): TokenAttrs {
                return new TokenAttrs();
            }],
            // MAX на успешной проверке 2FA может вернуть error=false вместе с
            // tokenAttrs. Храним поле без строгого string-cast, чтобы не
            // ломать успешный login из-за формы внешнего API.
            'error' => ['type' => 'mixed'],
        ];
    }

    public function loginToken(): ?string
    {
        return $this->tokenAttrs !== null && $this->tokenAttrs->login !== null
            ? $this->tokenAttrs->login->token
            : null;
    }
}
