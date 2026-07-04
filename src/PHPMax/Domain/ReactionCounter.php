<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class ReactionCounter extends Model
{
    /** @var int|null */
    public $count;
    /** @var string|null */
    public $reaction;

    protected static function schema(): array
    {
        return [
            'count' => ['type' => 'int', 'required' => true],
            'reaction' => ['type' => 'string', 'required' => true],
        ];
    }
}

