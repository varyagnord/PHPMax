<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Domain\AttachmentType;
use PHPMax\Support\Model;

class AttachPhotoPayload extends Model
{
    /** @var string */
    public $type = AttachmentType::PHOTO;
    /** @var string|null */
    public $photoToken;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'payload' => '_type', 'default' => AttachmentType::PHOTO],
            'photoToken' => ['type' => 'string', 'required' => true],
        ];
    }
}

