<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class PhotoAttachment extends BaseAttachment
{
    /** @var string|null */
    public $baseUrl;
    /** @var int|null */
    public $height;
    /** @var int|null */
    public $width;
    /** @var string|null */
    public $photoId;
    /** @var string|null */
    public $photoToken;
    /** @var string|null */
    public $previewData;

    protected static function schema(): array
    {
        return parent::schema() + [
            'baseUrl' => ['type' => 'string'],
            'height' => ['type' => 'int'],
            'width' => ['type' => 'int'],
            'photoId' => ['type' => 'string'],
            'photoToken' => ['type' => 'string', 'aliases' => ['token']],
            'previewData' => ['type' => 'string'],
        ];
    }
}
