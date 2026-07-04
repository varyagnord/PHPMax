<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Support\Model;

class FilePayloadResponse extends Model
{
    /** @var string|null */
    public $url;
    /** @var int|null */
    public $fileId;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'url' => ['type' => 'string', 'required' => true],
            'fileId' => ['type' => 'int', 'required' => true],
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

