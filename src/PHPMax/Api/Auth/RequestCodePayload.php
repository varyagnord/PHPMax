<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class RequestCodePayload extends Model
{
    /** @var string|null */
    public $phone;
    /** @var string|null */
    public $type;
    /** @var string|null */
    public $language;

    protected static function schema(): array
    {
        return [
            'phone' => ['type' => 'string', 'required' => true],
            'type' => ['type' => 'string', 'default' => AuthType::START_AUTH],
            'language' => ['type' => 'string', 'default' => 'ru'],
        ];
    }
}

