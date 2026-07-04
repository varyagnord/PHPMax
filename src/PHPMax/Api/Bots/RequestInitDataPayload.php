<?php

declare(strict_types=1);

namespace PHPMax\Api\Bots;

use PHPMax\Support\Model;

class RequestInitDataPayload extends Model
{
    /** @var int|null */
    public $botId;
    /** @var int|null */
    public $chatId;
    /** @var string|null */
    public $startParam;

    protected static function schema(): array
    {
        return [
            'botId' => ['type' => 'int', 'required' => true],
            'chatId' => ['type' => 'int'],
            'startParam' => ['type' => 'string'],
        ];
    }
}

