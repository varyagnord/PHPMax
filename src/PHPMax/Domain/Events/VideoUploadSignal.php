<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Support\Model;

class VideoUploadSignal extends Model
{
    /** @var int|null */
    public $videoId;

    protected static function schema(): array
    {
        return [
            'videoId' => ['type' => 'int', 'required' => true],
        ];
    }
}
