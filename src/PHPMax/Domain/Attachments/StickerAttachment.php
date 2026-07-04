<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class StickerAttachment extends BaseAttachment
{
    /** @var string|null */
    public $authorType;
    /** @var string|null */
    public $lottieUrl;
    /** @var string|null */
    public $url;
    /** @var int|null */
    public $stickerId;
    /** @var list<string>|null */
    public $tags;
    /** @var int|null */
    public $width;
    /** @var int|null */
    public $setId;
    /** @var int|null */
    public $time;
    /** @var string|null */
    public $stickerType;
    /** @var bool|null */
    public $audio;
    /** @var int|null */
    public $height;

    protected static function schema(): array
    {
        return parent::schema() + [
            'authorType' => ['type' => 'string'],
            'lottieUrl' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'stickerId' => ['type' => 'int'],
            'tags' => ['type' => 'list<string>'],
            'width' => ['type' => 'int'],
            'setId' => ['type' => 'int'],
            'time' => ['type' => 'int'],
            'stickerType' => ['type' => 'string'],
            'audio' => ['type' => 'bool'],
            'height' => ['type' => 'int'],
        ];
    }
}
