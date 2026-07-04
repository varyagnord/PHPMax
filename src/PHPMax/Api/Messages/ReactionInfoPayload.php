<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ReactionInfoPayload extends Model
{
    public $reactionType;
    public $id;

    protected static function schema(): array
    {
        return [
            'reactionType' => ['type' => 'string', 'default' => 'EMOJI'],
            'id' => ['type' => 'string', 'required' => true],
        ];
    }
}

