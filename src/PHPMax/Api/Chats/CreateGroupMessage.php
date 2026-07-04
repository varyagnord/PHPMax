<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class CreateGroupMessage extends Model
{
    /** @var int|null */
    public $cid;
    /** @var list<CreateGroupAttach> */
    public $attaches = [];

    protected static function schema(): array
    {
        return [
            'cid' => ['type' => 'int', 'required' => true],
            'attaches' => ['type' => 'list<' . CreateGroupAttach::class . '>', 'required' => true],
        ];
    }
}
