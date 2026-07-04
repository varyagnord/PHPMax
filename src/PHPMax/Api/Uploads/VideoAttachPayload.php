<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Domain\AttachmentType;
use PHPMax\Support\Model;

class VideoAttachPayload extends Model
{
    /** @var string */
    public $type = AttachmentType::VIDEO;
    /** @var int|null */
    public $videoId;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'payload' => '_type', 'default' => AttachmentType::VIDEO],
            'videoId' => ['type' => 'int', 'required' => true],
            'token' => ['type' => 'string', 'required' => true],
        ];
    }
}

