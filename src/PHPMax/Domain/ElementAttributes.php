<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class ElementAttributes extends Model
{
    /** @var string|null */
    public $url;

    protected static function schema(): array
    {
        return [
            'url' => ['type' => 'string'],
        ];
    }
}

