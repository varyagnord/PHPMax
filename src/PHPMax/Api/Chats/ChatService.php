<?php

declare(strict_types=1);

namespace PHPMax\Api\Chats;

use InvalidArgumentException;
use PHPMax\Api\Binding;
use PHPMax\Domain\Chat;
use PHPMax\Domain\Member;
use PHPMax\Domain\Message;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;

class ChatService
{
    /** @var App */
    private $app;
    /** @var int */
    private $prevCid;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->prevCid = (int) floor(microtime(true) * 1000);
    }

    public function cacheExternalChat(Chat $chat): Chat
    {
        return $this->cacheChat($chat);
    }

    /**
     * @param list<int>|null $participantIds
     * @return array{0: Chat, 1: Message}|null
     */
    public function createGroup(string $name, ?array $participantIds = null, bool $notify = true): ?array
    {
        $payload = new CreateGroupPayload([
            'message' => new CreateGroupMessage([
                'cid' => $this->nextCid(),
                'attaches' => [
                    new CreateGroupAttach([
                        'title' => $name,
                        'userIds' => $participantIds !== null ? $participantIds : [],
                    ]),
                ],
            ]),
            'notify' => $notify,
        ]);

        $response = $this->app->invoke(Opcode::MSG_SEND, $payload->toArray());
        $chat = $this->parseChat($response->payload, ChatPayloadKey::CHAT);
        if ($chat === null || $response->payload === null) {
            return null;
        }

        return [
            $this->cacheChat($chat),
            Binding::bindApiModel($this->app, Message::fromArray($response->payload)),
        ];
    }

    /**
     * @param list<int> $userIds
     */
    public function inviteUsersToGroup(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        $payload = new InviteUsersPayload([
            'chatId' => $chatId,
            'userIds' => $userIds,
            'showHistory' => $showHistory,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_MEMBERS_UPDATE, $payload->toArray());

        return $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));
    }

    /**
     * @param list<int> $userIds
     */
    public function inviteUsersToChannel(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        return $this->inviteUsersToGroup($chatId, $userIds, $showHistory);
    }

    /**
     * @param list<int> $userIds
     */
    public function removeUsersFromGroup(int $chatId, array $userIds, int $cleanMsgPeriod): bool
    {
        $payload = new RemoveUsersPayload([
            'chatId' => $chatId,
            'userIds' => $userIds,
            'cleanMsgPeriod' => $cleanMsgPeriod,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_MEMBERS_UPDATE, $payload->toArray());
        $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));

        return true;
    }

    public function changeGroupSettings(
        int $chatId,
        ?bool $allCanPinMessage = null,
        ?bool $onlyOwnerCanChangeIconTitle = null,
        ?bool $onlyAdminCanAddMember = null,
        ?bool $onlyAdminCanCall = null,
        ?bool $membersCanSeePrivateLink = null
    ): void {
        $payload = new ChangeGroupSettingsPayload([
            'chatId' => $chatId,
            'options' => new ChangeGroupSettingsOptions([
                'allCanPinMessage' => $allCanPinMessage,
                'onlyOwnerCanChangeIconTitle' => $onlyOwnerCanChangeIconTitle,
                'onlyAdminCanAddMember' => $onlyAdminCanAddMember,
                'onlyAdminCanCall' => $onlyAdminCanCall,
                'membersCanSeePrivateLink' => $membersCanSeePrivateLink,
            ]),
        ]);
        $response = $this->app->invoke(Opcode::CHAT_UPDATE, $payload->toArray());
        $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));
    }

    public function changeGroupProfile(int $chatId, ?string $name, ?string $description = null): void
    {
        $payload = new ChangeGroupProfilePayload([
            'chatId' => $chatId,
            'theme' => $name,
            'description' => $description,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_UPDATE, $payload->toArray());
        $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));
    }

    public function joinGroup(string $link): Chat
    {
        $processed = $this->processChatJoinLink($link);
        if ($processed === null) {
            throw new InvalidArgumentException('Invalid group link');
        }

        return $this->joinChat($processed);
    }

    public function joinChannel(string $link): Chat
    {
        $processed = $this->processChatJoinLink($link);

        return $this->joinChat($processed !== null ? $processed : $link);
    }

    public function resolveGroupByLink(string $link): ?Chat
    {
        $processed = $this->processChatJoinLink($link);
        if ($processed === null) {
            throw new InvalidArgumentException('Invalid group link');
        }

        $payload = new LinkInfoPayload(['link' => $processed]);
        $response = $this->app->invoke(Opcode::LINK_INFO, $payload->toArray());

        $chat = $this->parseChat($response->payload, ChatPayloadKey::CHAT);

        return $chat !== null ? Binding::bindApiModel($this->app, $chat) : null;
    }

    public function reworkInviteLink(int $chatId): Chat
    {
        $payload = new ReworkInviteLinkPayload(['chatId' => $chatId]);
        $response = $this->app->invoke(Opcode::CHAT_UPDATE, $payload->toArray());

        return $this->cacheChat($this->requireChat($response->payload, ChatPayloadKey::CHAT));
    }

    /**
     * @param list<int> $chatIds
     * @return list<Chat>
     */
    public function getChats(array $chatIds): array
    {
        $cached = [];
        $missed = [];
        foreach ($chatIds as $chatId) {
            $chat = $this->app->cachedChat($chatId);
            if ($chat !== null) {
                $cached[$chatId] = $chat;
            } else {
                $missed[] = $chatId;
            }
        }

        if ($missed !== []) {
            $payload = new GetChatInfoPayload(['chatIds' => $missed]);
            $response = $this->app->invoke(Opcode::CHAT_INFO, $payload->toArray());
            foreach ($this->parseChats($response->payload, ChatPayloadKey::CHATS) as $chat) {
                $chat = $this->cacheChat($chat);
                if ($chat->id !== null) {
                    $cached[$chat->id] = $chat;
                }
            }
        }

        $result = [];
        foreach ($chatIds as $chatId) {
            if (isset($cached[$chatId])) {
                $result[] = $cached[$chatId];
            }
        }

        return $result;
    }

    public function getChat(int $chatId): Chat
    {
        $chats = $this->getChats([$chatId]);
        if ($chats === []) {
            throw new PHPMaxException('Chat not found in response');
        }

        return $chats[0];
    }

    public function leaveGroup(int $chatId): void
    {
        $payload = new LeaveChatPayload(['chatId' => $chatId]);
        $this->app->invoke(Opcode::CHAT_LEAVE, $payload->toArray());
        $this->removeCachedChat($chatId);
    }

    public function leaveChannel(int $chatId): void
    {
        $this->leaveGroup($chatId);
    }

    /**
     * @return list<Chat>
     */
    public function fetchChats(?int $marker = null): array
    {
        $payload = new FetchChatsPayload([
            'marker' => $marker ? $marker : (int) floor(microtime(true) * 1000),
        ]);
        $response = $this->app->invoke(Opcode::CHATS_LIST, $payload->toArray());

        $chats = [];
        foreach ($this->parseChats($response->payload, ChatPayloadKey::CHATS) as $chat) {
            $chats[] = $this->cacheChat($chat);
        }

        return $chats;
    }

    /**
     * @return list<Member>
     */
    public function getJoinRequests(int $chatId, int $count = 100): array
    {
        $payload = new FetchJoinRequests(['chatId' => $chatId, 'count' => $count]);
        $response = $this->app->invoke(Opcode::CHAT_MEMBERS, $payload->toArray());
        $items = $response->payload[ChatPayloadKey::MEMBERS] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $members = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $members[] = Binding::bindApiModel($this->app, Member::fromArray($item));
            }
        }

        return $members;
    }

    /**
     * @param list<int> $userIds
     */
    public function confirmJoinRequests(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        $payload = new JoinRequestActionPayload([
            'chatId' => $chatId,
            'userIds' => $userIds,
            'showHistory' => $showHistory,
            'operation' => ChatMemberOperation::ADD,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_MEMBERS_UPDATE, $payload->toArray());

        return $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));
    }

    public function confirmJoinRequest(int $chatId, int $userId, bool $showHistory = true): ?Chat
    {
        return $this->confirmJoinRequests($chatId, [$userId], $showHistory);
    }

    /**
     * @param list<int> $userIds
     */
    public function declineJoinRequests(int $chatId, array $userIds): ?Chat
    {
        $payload = new JoinRequestActionPayload([
            'chatId' => $chatId,
            'userIds' => $userIds,
            'showHistory' => null,
            'operation' => ChatMemberOperation::REMOVE,
        ]);
        $response = $this->app->invoke(Opcode::CHAT_MEMBERS_UPDATE, $payload->toArray());

        return $this->cacheNullableChat($this->parseChat($response->payload, ChatPayloadKey::CHAT));
    }

    public function declineJoinRequest(int $chatId, int $userId): ?Chat
    {
        return $this->declineJoinRequests($chatId, [$userId]);
    }

    public function deleteChat(int $chatId, ?int $lastEventTime = null, bool $forAll = true): void
    {
        $payload = new DeleteChatPayload([
            'chatId' => $chatId,
            'lastEventTime' => $lastEventTime !== null ? $lastEventTime : (int) floor(microtime(true) * 1000),
            'forAll' => $forAll,
        ]);
        $this->app->invoke(Opcode::CHAT_DELETE, $payload->toArray());
        $this->removeCachedChat($chatId);
    }

    private function joinChat(string $link): Chat
    {
        $payload = new JoinChatPayload(['link' => $link]);
        $response = $this->app->invoke(Opcode::CHAT_JOIN, $payload->toArray());

        return $this->cacheChat($this->requireChat($response->payload, ChatPayloadKey::CHAT));
    }

    private function processChatJoinLink(string $link): ?string
    {
        $index = strpos($link, ChatLinkPrefix::JOIN);
        if ($index === false) {
            return null;
        }

        return substr($link, $index);
    }

    private function nextCid(): int
    {
        $now = (int) floor(microtime(true) * 1000);
        $next = max($now, $this->prevCid + 1);
        $this->prevCid = $next;

        return $next;
    }

    private function cacheChat(Chat $chat): Chat
    {
        return $this->app->cacheChat($chat);
    }

    private function cacheNullableChat(?Chat $chat): ?Chat
    {
        if ($chat === null) {
            return null;
        }

        return $this->cacheChat($chat);
    }

    private function removeCachedChat(int $chatId): void
    {
        $this->app->removeCachedChat($chatId);
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function parseChat(?array $payload, string $key): ?Chat
    {
        if ($payload === null || !isset($payload[$key]) || !is_array($payload[$key]) || $payload[$key] === []) {
            return null;
        }

        return Chat::fromArray($payload[$key]);
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function requireChat(?array $payload, string $key): Chat
    {
        $chat = $this->parseChat($payload, $key);
        if ($chat === null) {
            throw new PHPMaxException('Chat not found in response');
        }

        return $chat;
    }

    /**
     * @param array<mixed>|null $payload
     * @return list<Chat>
     */
    private function parseChats(?array $payload, string $key): array
    {
        $items = $payload[$key] ?? null;
        if ($items === null || $items === []) {
            return [];
        }
        if (!is_array($items)) {
            throw new PHPMaxException('Invalid chat list in response');
        }

        $chats = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new PHPMaxException('Invalid chat item in response');
            }
            $chats[] = Chat::fromArray($item);
        }

        return $chats;
    }
}
