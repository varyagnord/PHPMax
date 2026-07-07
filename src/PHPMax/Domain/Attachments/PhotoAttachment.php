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

    protected static function normalizeInput(array $data): array
    {
        foreach (['baseUrl', 'base_url', 'photoId', 'photo_id', 'photoToken', 'photo_token', 'token', 'previewData', 'preview_data'] as $key) {
            if (!array_key_exists($key, $data) || $data[$key] === null || is_string($data[$key]) || $data[$key] === false) {
                continue;
            }

            // MAX can send malformed optional photo metadata during auth sync.
            // Do not break login because of a preview/photo field we can safely ignore.
            $data[$key] = is_scalar($data[$key]) ? (string) $data[$key] : '';
        }

        return $data;
    }

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
