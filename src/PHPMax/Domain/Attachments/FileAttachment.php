<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class FileAttachment extends BaseAttachment
{
    /** @var int|null */
    public $fileId;
    /** @var string|null */
    public $name;
    /** @var int|null */
    public $size;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return parent::schema() + [
            'fileId' => ['type' => 'int'],
            'name' => ['type' => 'string'],
            'size' => ['type' => 'int'],
            'token' => ['type' => 'string'],
        ];
    }
}
