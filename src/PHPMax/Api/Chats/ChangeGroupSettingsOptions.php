<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use PHPMax\Support\Model;

class ChangeGroupSettingsOptions extends Model
{
    /** @var bool|null */
    public $onlyOwnerCanChangeIconTitle;
    /** @var bool|null */
    public $allCanPinMessage;
    /** @var bool|null */
    public $onlyAdminCanAddMember;
    /** @var bool|null */
    public $onlyAdminCanCall;
    /** @var bool|null */
    public $membersCanSeePrivateLink;

    protected static function schema(): array
    {
        return [
            'onlyOwnerCanChangeIconTitle' => ['type' => 'bool', 'payload' => ChatOption::ONLY_OWNER_CAN_CHANGE_ICON_TITLE],
            'allCanPinMessage' => ['type' => 'bool', 'payload' => ChatOption::ALL_CAN_PIN_MESSAGE],
            'onlyAdminCanAddMember' => ['type' => 'bool', 'payload' => ChatOption::ONLY_ADMIN_CAN_ADD_MEMBER],
            'onlyAdminCanCall' => ['type' => 'bool', 'payload' => ChatOption::ONLY_ADMIN_CAN_CALL],
            'membersCanSeePrivateLink' => ['type' => 'bool', 'payload' => ChatOption::MEMBERS_CAN_SEE_PRIVATE_LINK],
        ];
    }
}
