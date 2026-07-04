<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class CheckQrPayload extends Model
{
    /** @var string|null */
    public $trackId;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
        ];
    }
}
