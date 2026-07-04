<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;

class VideoUploadResponse extends Model
{
    /** @var list<VideoPayloadResponse> */
    public $info = [];

    protected static function schema(): array
    {
        return [
            'info' => ['factory' => static function ($value): array {
                if (!is_array($value) || !self::isListArray($value)) {
                    throw new ValidationException('Expected info list in video upload response');
                }
                $items = [];
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        throw new ValidationException('Expected video upload info item');
                    }
                    $items[] = VideoPayloadResponse::fromArray($item);
                }

                return $items;
            }, 'required' => true],
        ];
    }
}
