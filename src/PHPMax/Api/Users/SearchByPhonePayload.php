<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Support\Model;

class SearchByPhonePayload extends Model
{
    /** @var string|null */
    public $phone;

    protected static function schema(): array
    {
        return [
            'phone' => ['type' => 'string', 'required' => true],
        ];
    }
}
