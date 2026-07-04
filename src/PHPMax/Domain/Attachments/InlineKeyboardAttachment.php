<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class InlineKeyboardAttachment extends BaseAttachment
{
    /** @var array<string, mixed>|null */
    public $keyboard;

    protected static function schema(): array
    {
        return parent::schema() + [
            'keyboard' => ['type' => 'array'],
        ];
    }
}
