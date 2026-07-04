<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class VideoAttachment extends BaseAttachment
{
    /** @var int|null */
    public $height;
    /** @var int|null */
    public $width;
    /** @var int|null */
    public $videoId;
    /** @var int|null */
    public $duration;
    /** @var string|null */
    public $previewData;
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $thumbnail;
    /** @var int|null */
    public $videoType;
    protected static function schema(): array
    {
        return parent::schema() + [
            'height' => ['type' => 'int'],
            'width' => ['type' => 'int'],
            'videoId' => ['type' => 'int'],
            'duration' => ['type' => 'int'],
            'previewData' => ['type' => 'string'],
            'token' => ['type' => 'string'],
            'thumbnail' => ['type' => 'string'],
            'videoType' => ['type' => 'int'],
        ];
    }
}
