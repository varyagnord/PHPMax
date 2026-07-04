<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class CheckQrResponse extends Model
{
    /** @var QrStatus|null */
    public $status;

    protected static function schema(): array
    {
        return [
            'status' => ['type' => QrStatus::class, 'required' => true],
        ];
    }
}
