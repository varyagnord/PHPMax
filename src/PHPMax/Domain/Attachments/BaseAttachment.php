<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

use PHPMax\Support\Model;

abstract class BaseAttachment extends Model
{
    /** @var string|null */
    public $type;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'payload' => '_type', 'required' => true],
        ];
    }
}

