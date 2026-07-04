<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class AddReactionPayload extends Model
{
    public $chatId;
    public $messageId;
    public $reaction;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageId' => ['type' => 'string', 'required' => true],
            'reaction' => ['type' => ReactionInfoPayload::class, 'required' => true],
        ];
    }
}

