<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class ChangeGroupProfilePayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var string|null */
    public $theme;
    /** @var string|null */
    public $description;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'theme' => ['type' => 'string'],
            'description' => ['type' => 'string'],
        ];
    }
}
