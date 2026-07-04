<?php

declare(strict_types=1);

use PHPMax\Dispatch\EventType;
use PHPMax\Api\Chats\ChatPayloadKey;
use PHPMax\Api\Messages\MessagePayloadKey;
use PHPMax\Api\Session\DeviceType;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $path = __DIR__ . '/../../docs/phpmax/contracts.json';
    $json = file_get_contents($path);
    $assert($json !== false && $json !== '', 'Contract manifest must exist');
    $manifest = json_decode((string) $json, true);
    $assert(is_array($manifest), 'Contract manifest must be valid JSON');

    $commandConstants = (new ReflectionClass(Command::class))->getConstants();
    $opcodeConstants = (new ReflectionClass(Opcode::class))->getConstants();
    $eventTypeConstants = (new ReflectionClass(EventType::class))->getConstants();
    ksort($commandConstants);
    ksort($opcodeConstants);
    ksort($eventTypeConstants);

    $assertSame($commandConstants, $manifest['commands']);
    $assertSame($opcodeConstants, $manifest['opcodes']);
    $assertSame($eventTypeConstants, $manifest['event_types']);

    $assertSame(Opcode::SESSION_INIT, $manifest['opcodes']['SESSION_INIT']);
    $assertSame(Opcode::LOGIN, $manifest['opcodes']['LOGIN']);
    $assertSame(Opcode::MSG_SEND, $manifest['opcodes']['MSG_SEND']);
    $assertSame(Opcode::NOTIF_ATTACH, $manifest['opcodes']['NOTIF_ATTACH']);
    $assertSame(Opcode::GET_QR, $manifest['opcodes']['GET_QR']);
    $assertSame(Opcode::LOGIN_BY_QR, $manifest['opcodes']['LOGIN_BY_QR']);
    $assertSame(Command::REQUEST, $manifest['commands']['REQUEST']);
    $assertSame(EventType::MESSAGE_NEW, $manifest['event_types']['MESSAGE_NEW']);
    $assertSame(DeviceType::ANDROID, $manifest['api_enums']['session.DeviceType']['ANDROID']);
    $assertSame(ChatPayloadKey::MEMBERS, $manifest['api_enums']['chats.ChatPayloadKey']['MEMBERS']);
    $assertSame(MessagePayloadKey::MESSAGES_REACTIONS, $manifest['api_enums']['messages.MessagePayloadKey']['MESSAGES_REACTIONS']);

    $assertSame('resolve_message', $manifest['event_map']['NOTIF_MESSAGE']);
    $assertSame('resolve_message', $manifest['event_map']['MSG_EDIT']);
    $assertSame('resolve_attach', $manifest['event_map']['NOTIF_ATTACH']);
    $assertSame('resolve_reaction_update', $manifest['event_map']['NOTIF_MSG_REACTIONS_CHANGED']);

    $assert(isset($manifest['payload_models']['auth.RequestCodePayload']));
    $requestCode = $manifest['payload_models']['auth.RequestCodePayload'];
    $assert(in_array('phone', $requestCode['fields'], true));
    $assert(in_array('type', $requestCode['fields'], true));
    $assertSame('phone', $requestCode['payload_keys']['phone']);
    $assertSame('type', $requestCode['payload_keys']['type']);

    $assert(isset($manifest['payload_models']['messages.SendMessagePayload']));
    $sendMessage = $manifest['payload_models']['messages.SendMessagePayload'];
    $assert(in_array('chat_id', $sendMessage['fields'], true));
    $assert(in_array('message', $sendMessage['fields'], true));
    $assertSame('chatId', $sendMessage['payload_keys']['chat_id']);
    $assertSame('message', $sendMessage['payload_keys']['message']);

    $assert(isset($manifest['payload_models']['messages.ChatHistoryPayload']));
    $assertSame('from', $manifest['payload_models']['messages.ChatHistoryPayload']['payload_keys']['from_']);
    $assertSame('itemType', $manifest['payload_models']['messages.ChatHistoryPayload']['payload_keys']['item_type']);

    $assert(isset($manifest['payload_models']['uploads.AttachPhotoPayload']));
    $assertSame('_type', $manifest['payload_models']['uploads.AttachPhotoPayload']['payload_keys']['type']);
    $assertSame('photoToken', $manifest['payload_models']['uploads.AttachPhotoPayload']['payload_keys']['photo_token']);

    $assert(isset($manifest['payload_models']['session.MobileHandshakePayload']));
    $assertSame('mt_instanceid', $manifest['payload_models']['session.MobileHandshakePayload']['payload_keys']['mt_instance_id']);

    $assert(isset($manifest['payload_models']['uploads.UploadPayload']));
    $uploadPayload = $manifest['payload_models']['uploads.UploadPayload'];
    $assert(in_array('count', $uploadPayload['fields'], true));
    $assert(in_array('profile', $uploadPayload['fields'], true));
    $assertSame('count', $uploadPayload['payload_keys']['count']);
    $assertSame('profile', $uploadPayload['payload_keys']['profile']);

    $assert(isset($manifest['service_methods']) && is_array($manifest['service_methods']));
    $assertSame('sendMessage', $manifest['service_methods']['messages']['send_message']);
    $assertSame('fetchHistory', $manifest['service_methods']['messages']['fetch_history']);
    $assertSame('setTwoFactor', $manifest['service_methods']['auth']['set_2fa']);
    $assertSame('checkTwoFactor', $manifest['service_methods']['auth']['check_2fa']);
    $assertSame('requestProfilePhotoUploadUrl', $manifest['service_methods']['self']['request_profile_photo_upload_url']);
    $assertSame('uploadVideo', $manifest['service_methods']['uploads']['upload_video']);
    $assert(isset($manifest['service_method_params']) && is_array($manifest['service_method_params']));
    $assertSame(
        ['passwordOld', 'passwordNew'],
        $manifest['service_method_params']['auth']['change_password']['php_params']
    );
    $assertSame(
        ['chatId', 'forward', 'backward', 'backwardTime', 'forwardTime', 'from', 'itemType', 'getChat', 'getMessages', 'interactive'],
        $manifest['service_method_params']['messages']['fetch_history']['php_params']
    );

    $assert(isset($manifest['client_methods']) && is_array($manifest['client_methods']));
    $assertSame('sendMessage', $manifest['client_methods']['send_message']);
    $assertSame('getBotInitData', $manifest['client_methods']['get_bot_init_data']);
    $assertSame('checkTwoFactor', $manifest['client_methods']['check_2fa']);
    $assertSame('requestProfilePhotoUploadUrl', $manifest['client_methods']['request_profile_photo_upload_url']);
    $assert(isset($manifest['client_method_params']) && is_array($manifest['client_method_params']));
    $assertSame(
        ['passwordOld', 'passwordNew'],
        $manifest['client_method_params']['change_password']['php_params']
    );
    $assertSame('fromTime', $manifest['client_method_params']['fetch_history']['php_params'][5]);

    $assert(isset($manifest['domain_models']) && is_array($manifest['domain_models']));
    $assertSame('firstName', $manifest['domain_models']['Name']['payload_keys']['first_name']);
    $assertSame('lastName', $manifest['domain_models']['Name']['payload_keys']['last_name']);
    $assertSame('reactionInfo', $manifest['domain_models']['Message']['payload_keys']['reaction_info']);
    $assertSame('prevMessageId', $manifest['domain_models']['Message']['payload_keys']['prev_message_id']);
    $assertSame('lastEventTime', $manifest['domain_models']['Chat']['payload_keys']['last_event_time']);
    $assertSame('codeLength', $manifest['domain_models']['StartAuthResponse']['payload_keys']['code_length']);
    $assertSame('LOGIN', $manifest['domain_models']['TokenAttrs']['payload_keys']['login']);
    $assertSame('REGISTER', $manifest['domain_models']['TokenAttrs']['payload_keys']['register_token']);
    $assertSame('chats_sync', $manifest['domain_models']['SyncState']['payload_keys']['chats_sync']);
    $assertSame('config_hash', $manifest['domain_models']['SyncOverrides']['payload_keys']['config_hash']);
    $assertSame('_type', $manifest['domain_models']['VideoAttachment']['payload_keys']['type']);
    $assertSame('videoType', $manifest['domain_models']['VideoAttachment']['payload_keys']['video_type']);
    $assertSame('url', $manifest['domain_models']['FileRequest']['payload_keys']['url']);
    $assertSame('from', $manifest['domain_models']['Element']['payload_keys']['from_']);

    $assert(isset($manifest['event_models']) && is_array($manifest['event_models']));
    $assertSame('messageIds', $manifest['event_models']['MessageDeleteEvent']['payload_keys']['message_ids']);
    $assertSame('chatId', $manifest['event_models']['MessageDeleteEvent']['payload_keys']['chat_id']);
    $assertSame('totalCount', $manifest['event_models']['ReactionUpdateEvent']['payload_keys']['total_count']);
    $assertSame('fileId', $manifest['event_models']['FileUploadSignal']['payload_keys']['file_id']);
};
