<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Exception\ValidationException;
use PHPMax\Support\Model;

class FileUploadResponse extends Model
{
    /** @var list<FilePayloadResponse> */
    public $info = [];

    protected static function schema(): array
    {
        return [
            'info' => ['factory' => static function ($value): array {
                if (!is_array($value) || !self::isListArray($value)) {
                    throw new ValidationException('Expected info list in file upload response');
                }
                $items = [];
                foreach ($value as $item) {
                    if (!is_array($item)) {
                        throw new ValidationException('Expected file upload info item');
                    }
                    $items[] = FilePayloadResponse::fromArray($item);
                }

                return $items;
            }, 'required' => true],
        ];
    }
}
