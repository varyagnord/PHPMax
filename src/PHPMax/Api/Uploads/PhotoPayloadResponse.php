<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Support\Model;

class PhotoPayloadResponse extends Model
{
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

