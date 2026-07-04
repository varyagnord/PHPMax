<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Domain\AttachmentType;
use PHPMax\Domain\ChatType;
use PHPMax\Support\Model;

class CreateGroupAttach extends Model
{
    /** @var string|null */
    public $type;
    /** @var string|null */
    public $event;
    /** @var string|null */
    public $chatType;
    /** @var string|null */
    public $title;
    /** @var list<int> */
    public $userIds = [];

    protected static function schema(): array
    {
        return [
            'type' => ['type' => 'string', 'payload' => '_type', 'default' => AttachmentType::CONTROL],
            'event' => ['type' => 'string', 'default' => ControlEvent::NEW],
            'chatType' => ['type' => 'string', 'default' => ChatType::CHAT],
            'title' => ['type' => 'string', 'required' => true],
            'userIds' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
