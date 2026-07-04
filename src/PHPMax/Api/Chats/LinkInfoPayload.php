<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class LinkInfoPayload extends Model
{
    /** @var string|null */
    public $link;

    protected static function schema(): array
    {
        return [
            'link' => ['type' => 'string', 'required' => true],
        ];
    }
}
