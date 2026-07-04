<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class ReadState extends Model
{
    /** @var int|null */
    public $unread;
    /** @var int|null */
    public $mark;

    protected static function schema(): array
    {
        return [
            'unread' => ['type' => 'int', 'required' => true],
            'mark' => ['type' => 'int', 'required' => true],
        ];
    }
}

