<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class QrStatus extends Model
{
    /** @var int|null */
    public $expiresAt;
    /** @var bool|null */
    public $loginAvailable;

    protected static function schema(): array
    {
        return [
            'expiresAt' => ['type' => 'int', 'required' => true],
            'loginAvailable' => ['type' => 'bool'],
        ];
    }
}
