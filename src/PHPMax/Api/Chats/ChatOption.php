<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

final class ChatOption
{
    public const ONLY_OWNER_CAN_CHANGE_ICON_TITLE = 'ONLY_OWNER_CAN_CHANGE_ICON_TITLE';
    public const ALL_CAN_PIN_MESSAGE = 'ALL_CAN_PIN_MESSAGE';
    public const ONLY_ADMIN_CAN_ADD_MEMBER = 'ONLY_ADMIN_CAN_ADD_MEMBER';
    public const ONLY_ADMIN_CAN_CALL = 'ONLY_ADMIN_CAN_CALL';
    public const MEMBERS_CAN_SEE_PRIVATE_LINK = 'MEMBERS_CAN_SEE_PRIVATE_LINK';

    private function __construct()
    {
    }
}
