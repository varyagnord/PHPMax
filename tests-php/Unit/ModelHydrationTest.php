<?php

declare(strict_types=1);

use PHPMax\Domain\AttachmentType;
use PHPMax\Domain\AccessType;
use PHPMax\Domain\Attachments\AudioAttachment;
use PHPMax\Domain\Attachments\CallAttachment;
use PHPMax\Domain\Attachments\ContactAttachment;
use PHPMax\Domain\Attachments\ControlAttachment;
use PHPMax\Domain\Attachments\FileAttachment;
use PHPMax\Domain\Attachments\InlineKeyboardAttachment;
use PHPMax\Domain\Attachments\PhotoAttachment;
use PHPMax\Domain\Attachments\ShareAttachment;
use PHPMax\Domain\Attachments\StickerAttachment;
use PHPMax\Domain\Attachments\UnknownAttachment;
use PHPMax\Domain\Attachments\VideoAttachment;
use PHPMax\Api\Account\CreateFolderPayload;
use PHPMax\Api\Account\DeleteFolderPayload;
use PHPMax\Api\Chats\InviteUsersPayload;
use PHPMax\Api\Messages\GetMessagesPayload;
use PHPMax\Api\Messages\GetReactionsPayload;
use PHPMax\Api\Messages\SendMessagePayload;
use PHPMax\Api\Messages\SendMessagePayloadMessage;
use PHPMax\Domain\Chat;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\LoginResponse;
use PHPMax\Domain\Message;
use PHPMax\Domain\Profile;
use PHPMax\Domain\SyncOverrides;
use PHPMax\Domain\SyncState;
use PHPMax\Domain\TranscriptionStatus;
use PHPMax\Domain\User;
use PHPMax\Exception\ValidationException;
use PHPMax\Session\SessionInfo;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $message = Message::fromArray([
        'chatId' => 100,
        'message' => [
            'id' => '42',
            'time' => 123456,
            'type' => 'USER',
            'text' => 'hello',
            'attaches' => [
                ['_type' => AttachmentType::PHOTO, 'photoId' => 'photo-1', 'token' => 'token-1'],
                ['_type' => AttachmentType::FILE, 'fileId' => 77, 'name' => 'report.pdf', 'size' => 1024, 'token' => 'file-token'],
                ['_type' => AttachmentType::VIDEO, 'videoId' => 88, 'height' => 720, 'width' => 1280, 'duration' => 5, 'previewData' => 'preview', 'thumbnail' => 'thumb', 'token' => 'video-token', 'videoType' => 1],
                ['_type' => AttachmentType::AUDIO, 'audio_id' => 12, 'duration' => 3, 'wave' => 'wave', 'transcription_status' => TranscriptionStatus::SUCCESS, 'url' => 'audio-url', 'token' => 'audio-token'],
                ['_type' => AttachmentType::CONTACT, 'contact_id' => 700, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'name' => 'Ada L.', 'photo_url' => 'photo-url'],
                ['_type' => AttachmentType::STICKER, 'sticker_id' => 45, 'set_id' => 4, 'sticker_type' => 'STATIC', 'lottie_url' => 'lottie-url', 'author_type' => 'USER', 'url' => 'sticker-url', 'tags' => ['math'], 'width' => 64, 'height' => 48, 'time' => 9, 'audio' => false],
                ['_type' => AttachmentType::CONTROL, 'event' => 'chat_created'],
                ['_type' => AttachmentType::INLINE_KEYBOARD, 'keyboard' => ['buttons' => [['text' => 'Open']]]],
                ['_type' => AttachmentType::SHARE, 'url' => 'https://example.test', 'title' => 'Example', 'description' => 'Preview', 'image' => ['url' => 'image-url']],
                ['_type' => AttachmentType::CALL, 'duration' => 30, 'conversation_id' => 'call-1', 'contact_ids' => [1, 2]],
                ['type' => AttachmentType::CONTROL, 'event' => 'type_key_control'],
                ['_type' => 'FUTURE_KIND', 'raw' => true],
            ],
        ],
        'unread' => 2,
        'mark' => 777,
    ]);

    $assertSame(42, $message->id);
    $assertSame(100, $message->chatId);
    $assertSame('hello', $message->text);
    $assertSame(2, $message->unread);
    $assert($message->attaches[0] instanceof PhotoAttachment, 'Known photo attachment must hydrate to PhotoAttachment');
    $assertSame('token-1', $message->attaches[0]->photoToken);
    $malformedPhoto = PhotoAttachment::fromArray([
        '_type' => AttachmentType::PHOTO,
        'baseUrl' => ['bad' => 'shape'],
        'photoId' => 12345,
        'token' => ['bad' => 'token'],
        'previewData' => true,
    ]);
    $assertSame('', $malformedPhoto->baseUrl);
    $assertSame('12345', $malformedPhoto->photoId);
    $assertSame('', $malformedPhoto->photoToken);
    $assertSame('1', $malformedPhoto->previewData);
    $assert($message->attaches[1] instanceof FileAttachment, 'Known file attachment must hydrate to FileAttachment');
    $assertSame('file-token', $message->attaches[1]->token);
    $assert($message->attaches[2] instanceof VideoAttachment, 'Known video attachment must hydrate to VideoAttachment');
    $assertSame(1280, $message->attaches[2]->width);
    $assertSame('video-token', $message->attaches[2]->token);
    $assert($message->attaches[3] instanceof AudioAttachment, 'Known audio attachment must hydrate to AudioAttachment');
    $assertSame(12, $message->attaches[3]->audioId);
    $assertSame(TranscriptionStatus::SUCCESS, $message->attaches[3]->transcriptionStatus);
    $assertSame('audio-token', $message->attaches[3]->token);
    $assertSame(12, $message->attaches[3]->toArray()['audioId']);
    $assertSame(TranscriptionStatus::SUCCESS, $message->attaches[3]->toArray()['transcriptionStatus']);
    $assert($message->attaches[4] instanceof ContactAttachment, 'Known contact attachment must hydrate to ContactAttachment');
    $assertSame(700, $message->attaches[4]->contactId);
    $assertSame('Ada', $message->attaches[4]->firstName);
    $assertSame('photo-url', $message->attaches[4]->photoUrl);
    $assert($message->attaches[5] instanceof StickerAttachment, 'Known sticker attachment must hydrate to StickerAttachment');
    $assertSame(45, $message->attaches[5]->stickerId);
    $assertSame(4, $message->attaches[5]->setId);
    $assertSame('STATIC', $message->attaches[5]->stickerType);
    $assertSame(false, $message->attaches[5]->audio);
    $assert($message->attaches[6] instanceof ControlAttachment, 'Known control attachment must hydrate to ControlAttachment');
    $assertSame('chat_created', $message->attaches[6]->event);
    $assert($message->attaches[7] instanceof InlineKeyboardAttachment, 'Known inline keyboard attachment must hydrate to InlineKeyboardAttachment');
    $assertSame('Open', $message->attaches[7]->keyboard['buttons'][0]['text']);
    $assert($message->attaches[8] instanceof ShareAttachment, 'Known share attachment must hydrate to ShareAttachment');
    $assertSame('Example', $message->attaches[8]->title);
    $assertSame('image-url', $message->attaches[8]->image['url']);
    $assert($message->attaches[9] instanceof CallAttachment, 'Known call attachment must hydrate to CallAttachment');
    $assertSame('call-1', $message->attaches[9]->conversationId);
    $assertSame([1, 2], $message->attaches[9]->contactIds);
    $assertSame('call-1', $message->attaches[9]->toArray()['conversationId']);
    $assert($message->attaches[10] instanceof ControlAttachment, 'Known attachment type key must hydrate through AttachmentFactory');
    $assertSame('type_key_control', $message->attaches[10]->event);
    $assert($message->attaches[11] instanceof UnknownAttachment, 'Unknown attachment must stay available');
    $assertSame('FUTURE_KIND', $message->attaches[11]->type);
    $assertSame(true, $message->attaches[11]->extra()['raw']);
    $assertThrows(ValidationException::class, static function (): void {
        UnknownAttachment::fromArray(['_type' => AttachmentType::PHOTO]);
    }, 'Known attachment type must not hydrate as UnknownAttachment');

    $chat = Chat::fromArray([
        'id' => 5,
        'type' => 'CHAT',
        'status' => 'ACTIVE',
        'owner' => 1,
        'access' => AccessType::PRIVATE,
        'new_messages' => 4,
        'participants_count' => 8,
        'base_raw_icon_url' => 'raw-icon',
        'pinnedMessage' => [
            'id' => 9,
            'chatId' => 5,
            'time' => 123,
            'type' => 'USER',
            'text' => 'pin',
        ],
    ]);
    $assert($chat->pinnedMessage instanceof Message);
    $assertSame(9, $chat->pinnedMessage->id);
    $assertSame(AccessType::PRIVATE, $chat->access);
    $assertSame(4, $chat->newMessages);
    $assertSame(8, $chat->participantsCount);
    $assertSame('raw-icon', $chat->baseRawIconUrl);
    $assertSame(AccessType::PRIVATE, $chat->toArray()['access']);

    $profile = Profile::fromArray([
        'contact' => [
            'id' => 9,
            'names' => [['name' => 'Test User', 'type' => 'NICK']],
        ],
        'unknownProfileField' => 'kept',
    ]);
    $assert($profile->contact instanceof User);
    $assertSame(null, $profile->profileOptions);
    $assert(!array_key_exists('profileOptions', $profile->toArray()), 'Absent profileOptions must stay null and be omitted from payload');
    $assertSame('Test User', $profile->contact->names[0]->name);
    $assertSame('kept', $profile->extra()['unknownProfileField']);
    $assertSame('kept', $profile->toArray()['unknownProfileField']);

    $apiPayload = SendMessagePayload::fromArray([
        'chatId' => 77,
        'notify' => true,
        'message' => [
            'text' => 'hello',
            'cid' => 123,
            'futureNested' => 'must-not-leak',
        ],
        'futureTopLevel' => 'must-not-leak',
    ]);
    $assert($apiPayload->message instanceof SendMessagePayloadMessage, 'Nested API payload model must hydrate');
    $assertSame([], $apiPayload->extra(), 'API payload models must ignore unknown top-level fields like PyMax API BaseModel');
    $assertSame([], $apiPayload->message->extra(), 'Nested API payload models must ignore unknown fields');
    $assertSame([
        'chatId' => 77,
        'message' => [
            'text' => 'hello',
            'cid' => 123,
            'elements' => [],
            'attaches' => [],
        ],
        'notify' => true,
    ], $apiPayload->toArray());

    $sessionInfo = SessionInfo::fromArray([
        'token' => 'token',
        'deviceId' => 'device',
        'phone' => '+10000000000',
        'debugSecret' => 'must-not-persist',
    ]);
    $assertSame([], $sessionInfo->extra(), 'SessionInfo must ignore unknown storage keys like PyMax session BaseModel');
    $assert(!array_key_exists('debugSecret', $sessionInfo->toArray()), 'SessionInfo must not persist unknown top-level keys');

    $sync = (new SyncOverrides(['chatsSync' => 10]))->resolve(new SyncState([
        'chatsSync' => 1,
        'contactsSync' => 2,
        'draftsSync' => 3,
        'presenceSync' => 4,
        'configHash' => 'hash',
    ]));
    $assertSame(10, $sync->chatsSync);
    $assertSame(2, $sync->contactsSync);
    $assertSame('hash', $sync->configHash);
    $assertSame([
        'chats_sync' => 10,
        'contacts_sync' => 2,
        'drafts_sync' => 3,
        'presence_sync' => 4,
        'config_hash' => 'hash',
    ], $sync->toArray());

    $snakeSync = SyncState::fromArray([
        'chats_sync' => 21,
        'contacts_sync' => 22,
        'drafts_sync' => 23,
        'presence_sync' => 24,
        'config_hash' => 'snake-hash',
    ]);
    $assertSame(21, $snakeSync->chatsSync);
    $assertSame('snake-hash', $snakeSync->configHash);
    $assertSame(['config_hash' => 'override-hash'], (new SyncOverrides(['config_hash' => 'override-hash']))->toArray());

    $preservedSync = LoginResponse::fromArray([
        'profile' => ['contact' => ['id' => 77, 'names' => []]],
    ])->updateSyncState(new SyncState([
        'chatsSync' => 31,
        'contactsSync' => 32,
        'draftsSync' => 33,
        'presenceSync' => 34,
        'configHash' => 'current-hash',
    ]));
    $assertSame(31, $preservedSync->chatsSync);
    $assertSame(32, $preservedSync->contactsSync);
    $assertSame(33, $preservedSync->draftsSync);
    $assertSame(34, $preservedSync->presenceSync);
    $assertSame('current-hash', $preservedSync->configHash);

    $updatedSync = LoginResponse::fromArray([
        'profile' => ['contact' => ['id' => 78, 'names' => []]],
        'time' => 99,
        'config' => ['hash' => 'next-hash'],
    ])->updateSyncState(new SyncState([
        'chatsSync' => 41,
        'contactsSync' => 42,
        'draftsSync' => 43,
        'presenceSync' => 44,
        'configHash' => 'old-hash',
    ]));
    $assertSame(99, $updatedSync->chatsSync);
    $assertSame(99, $updatedSync->contactsSync);
    $assertSame(99, $updatedSync->draftsSync);
    $assertSame(99, $updatedSync->presenceSync);
    $assertSame('next-hash', $updatedSync->configHash);

    $snakeMessage = Message::fromArray([
        'chat_id' => 501,
        'message' => [
            'id' => 502,
            'chat_id' => 501,
            'time' => 1234567,
            'type' => 'USER',
            'text' => 'snake',
            'prev_message_id' => '501',
            'attaches' => [
                ['_type' => AttachmentType::PHOTO, 'photo_id' => 'photo-snake', 'photo_token' => 'token-snake'],
            ],
        ],
    ]);
    $assertSame(501, $snakeMessage->chatId);
    $assertSame('501', $snakeMessage->prevMessageId);
    $assert($snakeMessage->attaches[0] instanceof PhotoAttachment, 'snake_case attachment keys must hydrate');
    $assertSame('photo-snake', $snakeMessage->attaches[0]->photoId);
    $assertSame('token-snake', $snakeMessage->attaches[0]->photoToken);
    $assertSame('token-snake', $snakeMessage->attaches[0]->toArray()['photoToken']);

    $snakeProfile = Profile::fromArray([
        'contact' => [
            'id' => 903,
            'base_raw_url' => 'raw-url',
            'base_url' => 'base-url',
            'names' => [[
                'name' => 'Snake User',
                'first_name' => 'Snake',
                'last_name' => 'User',
                'type' => 'NICK',
            ]],
        ],
    ]);
    $assertSame('raw-url', $snakeProfile->contact->baseRawUrl);
    $assertSame('base-url', $snakeProfile->contact->baseUrl);
    $assertSame('Snake', $snakeProfile->contact->names[0]->firstName);
    $assertSame('User', $snakeProfile->contact->names[0]->lastName);
    $assertSame('Snake', $snakeProfile->contact->names[0]->toArray()['firstName']);
    $assertSame('User', $snakeProfile->contact->names[0]->toArray()['lastName']);

    $payload = GetMessagesPayload::fromArray([
        'chat_id' => 600,
        'message_ids' => [1, 2],
    ]);
    $assertSame(['chatId' => 600, 'messageIds' => [1, 2]], $payload->toArray());
    $coercedListPayload = GetMessagesPayload::fromArray([
        'chat_id' => 600,
        'message_ids' => ['1', '2.0', true],
    ]);
    $assertSame(['chatId' => 600, 'messageIds' => [1, 2, 1]], $coercedListPayload->toArray());
    $folderPayload = CreateFolderPayload::fromArray([
        'id' => 'folder',
        'title' => 'Folder',
        'include' => ['10'],
        'filters' => [['type' => 'unread']],
    ]);
    $assertSame(['id' => 'folder', 'title' => 'Folder', 'include' => [10], 'filters' => [['type' => 'unread']]], $folderPayload->toArray());
    $deleteFolderPayload = DeleteFolderPayload::fromArray(['folderIds' => ['a', 'b']]);
    $assertSame(['folderIds' => ['a', 'b']], $deleteFolderPayload->toArray());

    $coercedScalars = Message::fromArray([
        'id' => '42.0',
        'time' => true,
        'type' => 'USER',
        'ttl' => 'false',
    ]);
    $assertSame(42, $coercedScalars->id);
    $assertSame(1, $coercedScalars->time);
    $assertSame(false, $coercedScalars->ttl);

    $assertThrows(ValidationException::class, static function (): void {
        Chat::fromArray([]);
    }, 'Empty explicit payload for required model must fail like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => 'abc', 'time' => 2, 'type' => 'USER']);
    }, 'Non-numeric int payload must fail instead of casting to zero');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => '42.1', 'time' => 2, 'type' => 'USER']);
    }, 'Fractional int payload must fail like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => [], 'time' => 2, 'type' => 'USER']);
    }, 'Array int payload must fail instead of casting to one');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => 1, 'time' => 2, 'type' => 3]);
    }, 'Non-string string payload must fail like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => 1, 'time' => 2, 'type' => 'USER', 'ttl' => 2]);
    }, 'Boolean payload must reject non-0/1 integers like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => 1, 'time' => 2, 'type' => 'USER', 'ttl' => 'maybe']);
    }, 'Boolean payload must reject unknown strings like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        User::fromArray(['id' => 1, 'names' => 'bad']);
    }, 'Explicit malformed list field must fail instead of becoming empty list');
    $assertThrows(ValidationException::class, static function (): void {
        User::fromArray(['id' => 1, 'names' => ['primary' => ['name' => 'Bad']]]);
    }, 'Associative array must not be accepted for list fields');
    $assertThrows(ValidationException::class, static function (): void {
        GetMessagesPayload::fromArray(['chatId' => 1, 'messageIds' => ['first' => 2]]);
    }, 'Primitive list payloads must reject associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        GetMessagesPayload::fromArray(['chatId' => 1, 'messageIds' => ['abc']]);
    }, 'list<int> payload items must reject non-numeric values');
    $assertThrows(ValidationException::class, static function (): void {
        GetReactionsPayload::fromArray(['chatId' => 1, 'messageIds' => [1]]);
    }, 'list<string> payload items must reject non-string values like Pydantic');
    $assertThrows(ValidationException::class, static function (): void {
        InviteUsersPayload::fromArray(['chatId' => 1, 'userIds' => ['first' => 2], 'showHistory' => true]);
    }, 'Chat user id list payloads must reject associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        CreateFolderPayload::fromArray(['id' => 'folder', 'title' => 'Folder', 'include' => ['primary' => 10], 'filters' => []]);
    }, 'Account include list payloads must reject associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        CreateFolderPayload::fromArray(['id' => 'folder', 'title' => 'Folder', 'include' => [10], 'filters' => ['type' => 'unread']]);
    }, 'Account filter list payloads must reject associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        Profile::fromArray(['contact' => ['id' => 1, 'names' => []], 'profileOptions' => 'bad']);
    }, 'Explicit malformed array field must fail instead of becoming empty array');
    $assertThrows(ValidationException::class, static function (): void {
        LoginResponse::fromArray(['profile' => ['contact' => ['id' => 1, 'names' => []]], 'messages' => 'bad']);
    }, 'Explicit malformed map-list field must fail instead of becoming empty map');
    $assertThrows(ValidationException::class, static function (): void {
        LoginResponse::fromArray([
            'profile' => ['contact' => ['id' => 1, 'names' => []]],
            'messages' => [
                1 => [
                    'first' => ['id' => 2, 'time' => 3, 'type' => 'USER'],
                ],
            ],
        ]);
    }, 'Map-list values must be list-like arrays, not associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        LoginResponse::fromArray(['profile' => ['contact' => ['id' => 1, 'names' => []]], 'contacts' => ['bad']]);
    }, 'LoginResponse contacts allow nulls but not scalar contact items');
    $assertThrows(ValidationException::class, static function (): void {
        LoginResponse::fromArray([
            'profile' => ['contact' => ['id' => 1, 'names' => []]],
            'contacts' => [
                'primary' => ['id' => 2, 'names' => []],
            ],
        ]);
    }, 'LoginResponse contacts must be a list-like payload');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray(['id' => 1, 'time' => 2, 'type' => 'USER', 'attaches' => ['bad']]);
    }, 'Explicit malformed attachment item must fail instead of being preserved as raw scalar');
    $assertThrows(ValidationException::class, static function (): void {
        Message::fromArray([
            'id' => 1,
            'time' => 2,
            'type' => 'USER',
            'attaches' => [
                'control' => ['_type' => AttachmentType::CONTROL, 'event' => 'bad'],
            ],
        ]);
    }, 'Message attaches must be a list-like payload');
    $assertThrows(ValidationException::class, static function (): void {
        MessageDeleteEvent::fromArray(['chatId' => 1, 'messageIds' => 'bad']);
    }, 'MessageDeleteEvent messageIds must be a list-like payload');
    $assertThrows(ValidationException::class, static function (): void {
        MessageDeleteEvent::fromArray(['chatId' => 1, 'messageIds' => ['first' => 2]]);
    }, 'MessageDeleteEvent messageIds must not accept associative maps');
    $assertThrows(ValidationException::class, static function (): void {
        MessageDeleteEvent::fromArray(['chatId' => 1, 'messageIds' => [['bad']]]);
    }, 'MessageDeleteEvent messageIds must contain scalar ids');
};
