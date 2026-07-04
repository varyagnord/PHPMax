<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class GetVideoPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var int|string|null */
    public $messageId;
    /** @var int|null */
    public $videoId;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageId' => ['type' => 'mixed', 'required' => true],
            'videoId' => ['type' => 'int', 'required' => true],
        ];
    }
}

