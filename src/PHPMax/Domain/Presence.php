<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Presence extends Model
{
    /** @var int|null */
    public $seen;
    /** @var int|null */
    public $status;

    protected static function schema(): array
    {
        return [
            'seen' => ['type' => 'int'],
            'status' => ['type' => 'int'],
        ];
    }
}
