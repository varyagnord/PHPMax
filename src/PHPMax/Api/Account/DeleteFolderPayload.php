<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class DeleteFolderPayload extends Model
{
    /** @var list<string> */
    public $folderIds = [];

    protected static function schema(): array
    {
        return [
            'folderIds' => ['type' => 'list<string>', 'required' => true],
        ];
    }
}
