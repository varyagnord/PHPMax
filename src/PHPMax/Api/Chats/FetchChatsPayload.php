<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class FetchChatsPayload extends Model
{
    /** @var int|null */
    public $marker;

    protected static function schema(): array
    {
        return [
            'marker' => ['type' => 'int', 'required' => true],
        ];
    }
}
