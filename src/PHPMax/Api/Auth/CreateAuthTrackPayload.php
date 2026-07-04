<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class CreateAuthTrackPayload extends Model
{
    /** @var int|null */
    public $type;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'int', 'default' => 0],
        ];
    }
}
