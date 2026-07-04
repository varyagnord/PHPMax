<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class GetChatInfoPayload extends Model
{
    /** @var list<int> */
    public $chatIds = [];

    protected static function schema(): array
    {
        return [
            'chatIds' => ['type' => 'list<int>', 'required' => true],
        ];
    }
}
