<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Support\Model;

class FileUploadSignal extends Model
{
    /** @var int|null */
    public $fileId;

    protected static function schema(): array
    {
        return [
            'fileId' => ['type' => 'int', 'required' => true],
        ];
    }
}
