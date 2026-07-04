<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class ControlAttachment extends BaseAttachment
{
    /** @var string|null */
    public $event;

    protected static function schema(): array
    {
        return parent::schema() + [
            'event' => ['type' => 'string'],
        ];
    }
}
