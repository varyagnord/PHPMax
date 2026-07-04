<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class SetHintPayload extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $hint;

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'hint' => ['type' => 'string', 'required' => true],
        ];
    }
}
