<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class UploadPayload extends Model
{
    /** @var int */
    public $count = 1;
    /** @var bool */
    public $profile = false;

    protected static function schema(): array
    {
        return [
            'count' => ['type' => 'int', 'default' => 1],
            'profile' => ['type' => 'bool', 'default' => false],
        ];
    }
}
