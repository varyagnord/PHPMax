<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ChatHistoryPayload extends Model
{
    public $chatId;
    public $forward;
    public $backward;
    public $backwardTime;
    public $forwardTime;
    public $getChat;
    public $from;
    public $itemType;
    public $getMessages;
    public $interactive;

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'forward' => ['type' => 'int', 'required' => true],
            'backward' => ['type' => 'int', 'default' => 40],
            'backwardTime' => ['type' => 'int', 'default' => 0],
            'forwardTime' => ['type' => 'int', 'default' => 0],
            'getChat' => ['type' => 'bool', 'default' => false],
            'from' => ['type' => 'int', 'payload' => 'from', 'required' => true],
            'itemType' => ['type' => 'string', 'default' => ItemType::REGULAR],
            'getMessages' => ['type' => 'bool', 'default' => true],
            'interactive' => ['type' => 'bool', 'default' => false],
        ];
    }
}

