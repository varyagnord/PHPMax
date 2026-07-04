<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class FileRequest extends Model
{
    /** @var bool|null */
    public $unsafe;
    /** @var string|null */
    public $url;

    protected static function schema(): array
    {
        return [
            'unsafe' => ['type' => 'bool', 'required' => true],
            'url' => ['type' => 'string', 'required' => true],
        ];
    }
}

