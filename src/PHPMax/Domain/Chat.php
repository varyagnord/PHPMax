<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use InvalidArgumentException;
use PHPMax\Api\Chats\ChatService;
use PHPMax\Api\Messages\ItemType;
use PHPMax\Api\Messages\MessageService;
use PHPMax\Support\Model;
use RuntimeException;

class Chat extends Model
{
    /** @var int|null */
    public $id;
    /** @var string|null */
    public $type;
    /** @var string|null */
    public $status;
    /** @var int|null */
    public $owner;
    /** @var array<int|string, mixed> */
    public $participants = [];
    /** @var string|null */
    public $title;
    /** @var string|null */
    public $baseRawIconUrl;
    /** @var string|null */
    public $baseIconUrl;
    /** @var Message|null */
    public $lastMessage;
    /** @var Message|null */
    public $pinnedMessage;
    /** @var int */
    public $lastEventTime = 0;
    /** @var int */
    public $lastDelayedUpdateTime = 0;
    /** @var int */
    public $lastFireDelayedErrorTime = 0;
    /** @var int */
    public $created = 0;
    /** @var int|null */
    public $newMessages;
    /** @var string|null */
    public $link;
    /** @var string|null */
    public $access;
    /** @var int|null */
    public $restrictions;
    /** @var int */
    public $participantsCount = 0;
    /** @var string|null */
    public $description;
    /** @var mixed */
    public $options;
    /** @var int */
    public $joinTime = 0;
    /** @var int|null */
    public $invitedBy;
    /** @var int */
    public $modified = 0;
    /** @var int */
    public $messagesCount = 0;
    /** @var bool|null */
    public $hasBots;
    /** @var int|null */
    public $prevMessageId;
    /** @var array<int|string, mixed> */
    public $adminParticipants = [];
    /** @var list<int> */
    public $admins = [];
    /** @var int|null */
    public $cid;
    /** @var MessageService|null */
    private $messageActions;
    /** @var ChatService|null */
    private $chatActions;

    public function bind(MessageService $messageActions, ChatService $chatActions): self
    {
        $this->messageActions = $messageActions;
        $this->chatActions = $chatActions;
        if ($this->lastMessage !== null) {
            $this->lastMessage->bind($messageActions);
        }
        if ($this->pinnedMessage !== null) {
            $this->pinnedMessage->bind($messageActions);
        }

        return $this;
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function answer(string $text, ?int $replyTo = null, ?array $attachments = null, bool $notify = true): ?Message
    {
        [$messageActions] = $this->bound();

        return $messageActions->sendMessage($this->requireChatId(), $text, $replyTo, $attachments, $notify);
    }

    /**
     * @return list<Message>|null
     */
    public function history(
        int $forward = 0,
        int $backward = 40,
        int $backwardTime = 0,
        int $forwardTime = 0,
        ?int $from = null,
        string $itemType = ItemType::REGULAR,
        bool $getChat = false,
        bool $getMessages = true,
        bool $interactive = false
    ): ?array {
        [$messageActions] = $this->bound();

        return $messageActions->fetchHistory(
            $this->requireChatId(),
            $forward,
            $backward,
            $backwardTime,
            $forwardTime,
            $from,
            $itemType,
            $getChat,
            $getMessages,
            $interactive
        );
    }

    public function getMessage(int $messageId): ?Message
    {
        [$messageActions] = $this->bound();

        return $messageActions->getMessage($this->requireChatId(), $messageId);
    }

    /**
     * @param list<int> $messageIds
     * @return list<Message>
     */
    public function getMessages(array $messageIds): array
    {
        [$messageActions] = $this->bound();

        return $messageActions->getMessages($this->requireChatId(), $messageIds);
    }

    public function leave(): void
    {
        [, $chatActions] = $this->bound();
        $chatId = $this->requireChatId();
        if ($this->type === ChatType::DIALOG) {
            throw new RuntimeException('Cannot leave dialog');
        }
        if ($this->type === ChatType::CHAT) {
            $chatActions->leaveGroup($chatId);
            return;
        }
        if ($this->type === ChatType::CHANNEL) {
            $chatActions->leaveChannel($chatId);
            return;
        }

        throw new InvalidArgumentException('Unknown chat type=' . (string) $this->type);
    }

    public function delete(bool $forAll = true): void
    {
        [, $chatActions] = $this->bound();

        $chatActions->deleteChat($this->requireChatId(), $this->lastEventTime, $forAll);
    }

    /**
     * @param list<int> $userIds
     */
    public function invite(array $userIds, bool $showHistory = true): ?Chat
    {
        [, $chatActions] = $this->bound();
        $chatId = $this->requireChatId();
        if ($this->type === ChatType::CHAT) {
            return $chatActions->inviteUsersToGroup($chatId, $userIds, $showHistory);
        }
        if ($this->type === ChatType::CHANNEL) {
            return $chatActions->inviteUsersToChannel($chatId, $userIds, $showHistory);
        }

        throw new InvalidArgumentException('Unknown chat type=' . (string) $this->type);
    }

    /**
     * @param list<int> $userIds
     */
    public function removeUsers(array $userIds, int $cleanMsgPeriod = 0): bool
    {
        [, $chatActions] = $this->bound();

        return $chatActions->removeUsersFromGroup($this->requireChatId(), $userIds, $cleanMsgPeriod);
    }

    public function pinMessage(int $messageId, bool $notifyPin = true): bool
    {
        [$messageActions] = $this->bound();

        return $messageActions->pinMessage($this->requireChatId(), $messageId, $notifyPin);
    }

    public function updateSettings(
        ?bool $allCanPinMessage = null,
        ?bool $onlyOwnerCanChangeIconTitle = null,
        ?bool $onlyAdminCanAddMember = null,
        ?bool $onlyAdminCanCall = null,
        ?bool $membersCanSeePrivateLink = null
    ): void {
        [, $chatActions] = $this->bound();
        $chatActions->changeGroupSettings(
            $this->requireChatId(),
            $allCanPinMessage,
            $onlyOwnerCanChangeIconTitle,
            $onlyAdminCanAddMember,
            $onlyAdminCanCall,
            $membersCanSeePrivateLink
        );
    }

    public function reworkInviteLink(): Chat
    {
        [, $chatActions] = $this->bound();

        return $chatActions->reworkInviteLink($this->requireChatId());
    }

    public function isDialog(): bool
    {
        return $this->type === ChatType::DIALOG;
    }

    public function isGroup(): bool
    {
        return $this->type === ChatType::CHAT;
    }

    public function isChannel(): bool
    {
        return $this->type === ChatType::CHANNEL;
    }

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'int', 'required' => true],
            'type' => ['type' => 'string', 'required' => true],
            'status' => ['type' => 'string', 'required' => true],
            'owner' => ['type' => 'int', 'required' => true],
            'participants' => ['type' => 'array', 'default' => static function (): array {
                return [];
            }],
            'title' => ['type' => 'string'],
            'baseRawIconUrl' => ['type' => 'string'],
            'baseIconUrl' => ['type' => 'string'],
            'lastMessage' => ['type' => Message::class],
            'pinnedMessage' => ['type' => Message::class],
            'lastEventTime' => ['type' => 'int', 'default' => 0],
            'lastDelayedUpdateTime' => ['type' => 'int', 'default' => 0],
            'lastFireDelayedErrorTime' => ['type' => 'int', 'default' => 0],
            'created' => ['type' => 'int', 'default' => 0],
            'newMessages' => ['type' => 'int', 'default' => 0],
            'link' => ['type' => 'string'],
            'access' => ['type' => 'string'],
            'restrictions' => ['type' => 'int'],
            'participantsCount' => ['type' => 'int', 'default' => 0],
            'description' => ['type' => 'string'],
            'options' => ['type' => 'mixed'],
            'joinTime' => ['type' => 'int', 'default' => 0],
            'invitedBy' => ['type' => 'int'],
            'modified' => ['type' => 'int', 'default' => 0],
            'messagesCount' => ['type' => 'int', 'default' => 0],
            'hasBots' => ['type' => 'bool'],
            'prevMessageId' => ['type' => 'int'],
            'adminParticipants' => ['type' => 'array', 'default' => static function (): array {
                return [];
            }],
            'admins' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
            'cid' => ['type' => 'int'],
        ];
    }

    /**
     * @return array{0: MessageService, 1: ChatService}
     */
    private function bound(): array
    {
        if ($this->messageActions === null || $this->chatActions === null) {
            throw new RuntimeException('Chat is not bound to a client.');
        }

        return [$this->messageActions, $this->chatActions];
    }

    private function requireChatId(): int
    {
        if ($this->id === null) {
            throw new RuntimeException('Chat does not contain id.');
        }

        return $this->id;
    }
}
