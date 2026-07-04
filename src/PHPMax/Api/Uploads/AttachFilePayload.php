<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Domain\AttachmentType;
use PHPMax\Support\Model;

class AttachFilePayload extends Model
{
    /** @var string */
    public $type = AttachmentType::FILE;
    /** @var int|null */
    public $fileId;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'payload' => '_type', 'default' => AttachmentType::FILE],
            'fileId' => ['type' => 'int', 'required' => true],
        ];
    }
}

