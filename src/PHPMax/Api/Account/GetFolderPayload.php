<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class GetFolderPayload extends Model
{
    /** @var int */
    public $folderSync = 0;

    protected static function schema(): array
    {
        return [
            'folderSync' => ['type' => 'int', 'default' => 0],
        ];
    }
}
