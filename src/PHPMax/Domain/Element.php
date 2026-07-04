<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Element extends Model
{
    /** @var string|null */
    public $type;
    /** @var int|null */
    public $from;
    /** @var int|null */
    public $length;
    /** @var ElementAttributes|null */
    public $attributes;

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'required' => true],
            'from' => ['type' => 'int', 'payload' => 'from'],
            'length' => ['type' => 'int'],
            'attributes' => ['type' => ElementAttributes::class],
        ];
    }
}

