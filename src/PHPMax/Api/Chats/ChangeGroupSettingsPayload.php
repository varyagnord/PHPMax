<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class ChangeGroupSettingsPayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var ChangeGroupSettingsOptions|null */
    public $options;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'options' => ['type' => ChangeGroupSettingsOptions::class, 'required' => true],
        ];
    }
}
