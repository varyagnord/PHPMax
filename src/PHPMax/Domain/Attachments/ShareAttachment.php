<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class ShareAttachment extends BaseAttachment
{
    /** @var string|null */
    public $url;
    /** @var string|null */
    public $title;
    /** @var string|null */
    public $description;
    /** @var array<string, mixed>|null */
    public $image;

    protected static function schema(): array
    {
        return parent::schema() + [
            'url' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'image' => ['type' => 'array'],
        ];
    }
}
