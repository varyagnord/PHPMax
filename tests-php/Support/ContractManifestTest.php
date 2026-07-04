<?php

declare(strict_types=1);

use PHPMax\Domain\AccessType;
use PHPMax\Domain\AttachmentType;
use PHPMax\Domain\ChatType;
use PHPMax\Domain\MessageStatus;
use PHPMax\Domain\TranscriptionStatus;
use PHPMax\Api\Account\AvatarType;
use PHPMax\Api\Account\SelfPayloadKey;
use PHPMax\Api\Auth\AuthType;
use PHPMax\Api\Auth\ProfileOptions;
use PHPMax\Api\Auth\TwoFactorAction;
use PHPMax\Api\Chats\ChatLinkPrefix;
use PHPMax\Api\Chats\ChatMemberOperation;
use PHPMax\Api\Chats\ChatOption;
use PHPMax\Api\Chats\ChatPayloadKey;
use PHPMax\Api\Chats\ControlEvent;
use PHPMax\Api\Messages\ItemType;
use PHPMax\Api\Messages\MessagePayloadKey;
use PHPMax\Api\Messages\ReadAction;
use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Users\ContactAction;
use PHPMax\Api\Users\UserPayloadKey;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $repoRoot = dirname(__DIR__, 2);
    $manifest = json_decode((string) file_get_contents($repoRoot . '/docs/phpmax/contracts.json'), true);

    $assert(is_array($manifest), 'contracts.json must be valid JSON');
    $assert(isset($manifest['domain_enums']) && is_array($manifest['domain_enums']), 'contracts.json must include domain_enums');
    $assert(isset($manifest['api_enums']) && is_array($manifest['api_enums']), 'contracts.json must include api_enums');
    $assert(isset($manifest['payload_models']) && is_array($manifest['payload_models']), 'contracts.json must include payload_models');
    $assert(isset($manifest['service_methods']) && is_array($manifest['service_methods']), 'contracts.json must include service_methods');
    $assert(isset($manifest['client_methods']) && is_array($manifest['client_methods']), 'contracts.json must include client_methods');
    $assert(isset($manifest['service_method_params']) && is_array($manifest['service_method_params']), 'contracts.json must include service_method_params');
    $assert(isset($manifest['client_method_params']) && is_array($manifest['client_method_params']), 'contracts.json must include client_method_params');
    $assert(isset($manifest['domain_models']) && is_array($manifest['domain_models']), 'contracts.json must include domain_models');
    $assert(isset($manifest['event_models']) && is_array($manifest['event_models']), 'contracts.json must include event_models');

    $domainClasses = [
        'AccessType' => AccessType::class,
        'AttachmentType' => AttachmentType::class,
        'ChatType' => ChatType::class,
        'MessageStatus' => MessageStatus::class,
        'TranscriptionStatus' => TranscriptionStatus::class,
    ];

    foreach ($domainClasses as $name => $className) {
        $assert(isset($manifest['domain_enums'][$name]) && is_array($manifest['domain_enums'][$name]), 'Missing domain enum manifest for ' . $name);
        $constants = (new ReflectionClass($className))->getConstants();
        ksort($constants);
        $assertSame($manifest['domain_enums'][$name], $constants, 'Domain enum constants must match manifest for ' . $name);
    }

    $assertSame([
        'EDITED' => 'EDITED',
        'REMOVED' => 'REMOVED',
    ], $manifest['domain_enums']['MessageStatus'], 'MessageStatus must mirror PyMax exactly');

    $apiClasses = [
        'auth.AuthType' => AuthType::class,
        'auth.ProfileOptions' => ProfileOptions::class,
        'auth.TwoFactorAction' => TwoFactorAction::class,
        'chats.ChatLinkPrefix' => ChatLinkPrefix::class,
        'chats.ChatMemberOperation' => ChatMemberOperation::class,
        'chats.ChatOption' => ChatOption::class,
        'chats.ChatPayloadKey' => ChatPayloadKey::class,
        'chats.ControlEvent' => ControlEvent::class,
        'messages.ItemType' => ItemType::class,
        'messages.MessagePayloadKey' => MessagePayloadKey::class,
        'messages.ReadAction' => ReadAction::class,
        'self.AvatarType' => AvatarType::class,
        'self.SelfPayloadKey' => SelfPayloadKey::class,
        'session.DeviceType' => DeviceType::class,
        'users.ContactAction' => ContactAction::class,
        'users.UserPayloadKey' => UserPayloadKey::class,
    ];

    foreach ($apiClasses as $name => $className) {
        $assert(isset($manifest['api_enums'][$name]) && is_array($manifest['api_enums'][$name]), 'Missing API enum manifest for ' . $name);
        $constants = (new ReflectionClass($className))->getConstants();
        ksort($constants);
        $assertSame($manifest['api_enums'][$name], $constants, 'API enum constants must match manifest for ' . $name);
    }

    $assertSame('reactionInfo', $manifest['api_enums']['messages.MessagePayloadKey']['REACTION_INFO']);
    $assertSame(5, $manifest['api_enums']['auth.TwoFactorAction']['REMOVE_2FA']);
    $assertSame('sessions', $manifest['api_enums']['users.UserPayloadKey']['SESSIONS']);
    $assertSame('chatId', $manifest['payload_models']['messages.SendMessagePayload']['payload_keys']['chat_id']);
    $assertSame('_type', $manifest['payload_models']['chats.CreateGroupAttach']['payload_keys']['type']);
    $assertSame('ONLY_ADMIN_CAN_ADD_MEMBER', $manifest['payload_models']['chats.ChangeGroupSettingsOptions']['payload_keys']['only_admin_can_add_member']);
    $assertSame('firstName', $manifest['payload_models']['users._ContactPayload']['payload_keys']['first_name']);
    $assertSame('checkTwoFactor', $manifest['service_methods']['auth']['check_2fa']);
    $assertSame('getInitData', $manifest['service_methods']['bots']['get_init_data']);
    $assertSame('deleteChat', $manifest['service_methods']['chats']['delete_chat']);
    $assertSame('checkTwoFactor', $manifest['client_methods']['check_2fa']);
    $assertSame('getBotInitData', $manifest['client_methods']['get_bot_init_data']);
    $assertSame('fetchHistory', $manifest['client_methods']['fetch_history']);
    $assertSame(['passwordOld', 'passwordNew'], $manifest['service_method_params']['auth']['change_password']['php_params']);
    $assertSame(['passwordOld', 'passwordNew'], $manifest['client_method_params']['change_password']['php_params']);
    $assertSame('fromTime', $manifest['client_method_params']['fetch_history']['php_params'][5]);
    $assertSame('firstName', $manifest['domain_models']['Name']['payload_keys']['first_name']);
    $assertSame('reactionInfo', $manifest['domain_models']['Message']['payload_keys']['reaction_info']);
    $assertSame('profileOptions', $manifest['domain_models']['Profile']['payload_keys']['profile_options']);
    $assertSame('LOGIN', $manifest['domain_models']['TokenAttrs']['payload_keys']['login']);
    $assertSame('chats_sync', $manifest['domain_models']['SyncState']['payload_keys']['chats_sync']);
    $assertSame('_type', $manifest['domain_models']['PhotoAttachment']['payload_keys']['type']);
    $assertSame('videoType', $manifest['domain_models']['VideoAttachment']['payload_keys']['video_type']);
    $assertSame('queryId', $manifest['domain_models']['InitData']['payload_keys']['query_id']);
    $assertSame('from', $manifest['domain_models']['Element']['payload_keys']['from_']);
    $assertSame('messageIds', $manifest['event_models']['MessageDeleteEvent']['payload_keys']['message_ids']);
    $assertSame('totalCount', $manifest['event_models']['ReactionUpdateEvent']['payload_keys']['total_count']);
    $assertSame('videoId', $manifest['event_models']['VideoUploadSignal']['payload_keys']['video_id']);

    $checkOutput = [];
    $checkExit = 1;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($repoRoot . '/tools/contract-manifest.php') . ' check', $checkOutput, $checkExit);
    $assertSame(0, $checkExit, 'contract manifest check must pass');
};
