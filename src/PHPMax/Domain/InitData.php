<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class InitData extends Model
{
    /** @var string|null */
    public $queryId;
    /** @var string|null */
    public $url;

    protected static function schema(): array
    {
        return [
            'queryId' => ['type' => 'string', 'required' => true],
            'url' => ['type' => 'string', 'required' => true],
        ];
    }
}

