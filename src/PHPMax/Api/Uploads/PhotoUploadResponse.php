<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;

class PhotoUploadResponse extends Model
{
    /** @var array<string, PhotoPayloadResponse> */
    public $photos = [];

    protected static function schema(): array
    {
        return [
            'photos' => ['factory' => static function ($value): array {
                $result = [];
                if (!is_array($value)) {
                    throw new ValidationException('Expected photos map in photo upload response');
                }
                foreach ($value as $photoId => $item) {
                    if (is_array($item)) {
                        $result[(string) $photoId] = PhotoPayloadResponse::fromArray($item);
                    }
                }

                return $result;
            }, 'required' => true],
        ];
    }
}
