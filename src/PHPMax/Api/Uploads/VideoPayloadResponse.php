<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Support\Model;

class VideoPayloadResponse extends Model
{
    /** @var string|null */
    public $url;
    /** @var int|null */
    public $videoId;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'url' => ['type' => 'string', 'required' => true],
            'videoId' => ['type' => 'int', 'required' => true],
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

