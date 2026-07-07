<?php

declare(strict_types=1);

namespace PHPMax;

use PHPMax\Auth\EmailCodeProviderInterface;
use PHPMax\Api\Uploads\AttachFilePayload;
use PHPMax\Api\Uploads\AttachPhotoPayload;
use PHPMax\Api\Uploads\VideoAttachPayload;
use PHPMax\Config\ClientOptions;
use PHPMax\Api\Messages\ItemType;
use PHPMax\Api\Telemetry\RouteProfile;
use PHPMax\Api\Telemetry\TelemetryEvent;
use PHPMax\Domain\Chat;
use PHPMax\Domain\ContactInfo;
use PHPMax\Domain\FileRequest;
use PHPMax\Domain\FolderList;
use PHPMax\Domain\FolderUpdate;
use PHPMax\Domain\InitData;
use PHPMax\Domain\LoginResponse;
use PHPMax\Domain\Member;
use PHPMax\Domain\Message;
use PHPMax\Domain\ReactionInfo;
use PHPMax\Domain\ReadState;
use PHPMax\Domain\Profile;
use PHPMax\Domain\Session;
use PHPMax\Domain\User;
use PHPMax\Domain\VideoRequest;
use PHPMax\Dispatch\ErrorScope;
use PHPMax\Dispatch\EventType;
use PHPMax\Dispatch\Router;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Exception\ProtocolException;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Runtime\ExecutionBudget;
use PHPMax\Session\SessionInfo;
use PHPMax\Transport\TcpTransport;
use Throwable;

class Client
{
    /** @var ClientOptions */
    private $options;
    /** @var ConnectionManager */
    private $connection;
    /** @var Router */
    private $router;
    /** @var App */
    private $app;
    /** @var LoginResponse|null */
    private $loginResponse;

    public function __construct(?ClientOptions $options = null, ?ConnectionManager $connection = null, ?Router $router = null)
    {
        $this->options = $options ?: new ClientOptions();
        $this->connection = $connection ?: new ConnectionManager(new TcpTransport(
            $this->options->host,
            $this->options->port,
            $this->options->useSsl,
            $this->options->connectTimeout,
            $this->options->proxy
        ));
        $this->router = $router ?: new Router();
        $this->app = new App($this->connection, $this->options);
        $this->loginResponse = null;
        $client = $this;
        $this->connection->setEventHandler(static function (InboundFrame $frame) use ($client): void {
            $client->router()->dispatchFrame($frame, $client);
        });
    }

    public function open(): void
    {
        $this->connection->open();
        try {
            $this->authenticateIfConfigured();
        } catch (Throwable $e) {
            $handled = $this->router->emitError($e, EventType::ON_START, null, $this, $this->router, null);
            $this->close();
            if (!$handled) {
                throw $e;
            }
            return;
        }

        try {
            $this->router->emitStart($this);
        } catch (Throwable $e) {
            $this->close();
            throw $e;
        }
    }

    public function close(): void
    {
        $this->app->close();
    }

    public function stop(): void
    {
        $this->close();
    }

    /**
     * @return mixed
     */
    public function withOpenSession(callable $callback)
    {
        $this->open();
        try {
            return $callback($this);
        } finally {
            $this->close();
        }
    }

    public function runFor(int $seconds): void
    {
        $budget = ExecutionBudget::fromRequestedSeconds($seconds, $this->options->executionSafetyMargin);
        $nextPingAt = $this->nextPingDeadline();
        $startedAt = microtime(true);
        $this->traceRuntime('run_start', $startedAt, $budget, [
            'seconds' => $seconds,
            'ping_interval_ms' => (int) round($this->options->pingInterval * 1000),
            'request_timeout_ms' => (int) round($this->options->requestTimeout * 1000),
            'safety_margin_ms' => (int) round($this->options->executionSafetyMargin * 1000),
        ]);
        while (!$budget->expired() && $this->connection->isOpen()) {
            $readTimeout = $this->runReadTimeout($budget, $nextPingAt);
            try {
                $this->traceRuntime('read_wait', $startedAt, $budget, [
                    'read_timeout_ms' => (int) round($readTimeout * 1000),
                    'next_ping_due_ms' => $this->millisecondsUntil($nextPingAt),
                ]);
                $frame = $this->connection->readFrame($readTimeout);
            } catch (ProtocolException $e) {
                if ($this->isTimeoutException($e)) {
                    $this->traceRuntime('read_timeout', $startedAt, $budget, [
                        'error' => $this->shortError($e),
                        'next_ping_due_ms' => $this->millisecondsUntil($nextPingAt),
                    ]);
                    $nextPingAt = $this->runPingIfDue($nextPingAt, $budget, $startedAt);
                    continue;
                }
                $this->traceRuntime('read_error', $startedAt, $budget, [
                    'error' => $this->shortError($e),
                    'connection_open' => $this->connection->isOpen() ? 1 : 0,
                ]);
                if (!$this->handleRunDisconnect($e, $budget)) {
                    $this->traceRuntime('read_error_unhandled', $startedAt, $budget, [
                        'error' => $this->shortError($e),
                    ]);
                    throw $e;
                }
                $this->traceRuntime('read_error_reconnected', $startedAt, $budget, [
                    'error' => $this->shortError($e),
                    'connection_open' => $this->connection->isOpen() ? 1 : 0,
                ]);
                $nextPingAt = $this->nextPingDeadline();
                continue;
            }
            $this->traceRuntime('frame_received', $startedAt, $budget, [
                'opcode' => $frame->opcode,
                'cmd' => $frame->cmd,
                'seq' => $frame->seq,
                'field_keys' => $this->payloadKeySummary($frame->payload),
            ]);
            $this->connection->dispatchEvent($frame);
            $nextPingAt = $this->runPingIfDue($nextPingAt, $budget, $startedAt);
        }
        $this->traceRuntime('run_end', $startedAt, $budget, [
            'connection_open' => $this->connection->isOpen() ? 1 : 0,
            'budget_expired' => $budget->expired() ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function invoke(int $opcode, array $payload): InboundFrame
    {
        return $this->app->invoke($opcode, $payload);
    }

    public function login(): ?LoginResponse
    {
        $this->authenticateIfConfigured(true);

        return $this->loginResponse;
    }

    public function relogin(bool $dropConfigToken = true, bool $open = true): void
    {
        $session = $this->app->session();
        if ($session === null) {
            throw new PHPMaxException('Cannot relogin before session is loaded');
        }

        $this->app->store()->deleteSession($session->token);
        $this->close();

        if ($dropConfigToken) {
            $this->options->token = null;
        }

        $this->app->setSession(null);
        $this->app->clearState();
        $this->loginResponse = null;

        if ($open) {
            $this->open();
        }
    }

    public function me(): ?Profile
    {
        return $this->app->me();
    }

    /**
     * @return list<Chat>|null
     */
    public function chats(): ?array
    {
        return $this->app->chats();
    }

    /**
     * @return list<User|null>
     */
    public function contacts(): array
    {
        return $this->app->contacts();
    }

    /**
     * @return array<int|string, list<Message>>
     */
    public function messages(): array
    {
        return $this->app->messages();
    }

    public function includeRouter(Router $router): self
    {
        $this->router->includeRouter($router);

        return $this;
    }

    public function onRaw(callable $handler, callable ...$filters): self
    {
        $this->router->onRaw($handler, ...$filters);

        return $this;
    }

    public function onMessage(callable $handler, callable ...$filters): self
    {
        $this->router->onMessage($handler, ...$filters);

        return $this;
    }

    public function onMessageEdit(callable $handler, callable ...$filters): self
    {
        $this->router->onMessageEdit($handler, ...$filters);

        return $this;
    }

    public function onMessageDelete(callable $handler, callable ...$filters): self
    {
        $this->router->onMessageDelete($handler, ...$filters);

        return $this;
    }

    public function onMessageRead(callable $handler, callable ...$filters): self
    {
        $this->router->onMessageRead($handler, ...$filters);

        return $this;
    }

    public function onTyping(callable $handler, callable ...$filters): self
    {
        $this->router->onTyping($handler, ...$filters);

        return $this;
    }

    public function onPresence(callable $handler, callable ...$filters): self
    {
        $this->router->onPresence($handler, ...$filters);

        return $this;
    }

    public function onReactionUpdate(callable $handler, callable ...$filters): self
    {
        $this->router->onReactionUpdate($handler, ...$filters);

        return $this;
    }

    public function onChatUpdate(callable $handler, callable ...$filters): self
    {
        $this->router->onChatUpdate($handler, ...$filters);

        return $this;
    }

    public function onFileReady(callable $handler, callable ...$filters): self
    {
        $this->router->onFileReady($handler, ...$filters);

        return $this;
    }

    public function onVideoReady(callable $handler, callable ...$filters): self
    {
        $this->router->onVideoReady($handler, ...$filters);

        return $this;
    }

    public function onError(callable $handler, string $scope = ErrorScope::GLOBAL): self
    {
        $this->router->onError($handler, $scope);

        return $this;
    }

    public function onDisconnect(callable $handler): self
    {
        $this->router->onDisconnect($handler);

        return $this;
    }

    public function emitDisconnect(Throwable $exception, bool $reconnect = false, float $delay = 0.0): void
    {
        $this->router->emitDisconnect($exception, $reconnect, $delay);
    }

    public function onStart(callable $handler): self
    {
        $this->router->onStart($handler);

        return $this;
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function sendMessage(int $chatId, string $text, ?int $replyTo = null, ?array $attachments = null, bool $notify = true): ?Message
    {
        return $this->app->api()->messages->sendMessage($chatId, $text, $replyTo, $attachments, $notify);
    }

    /**
     * @param list<int> $messageIds
     * @return list<Message>
     */
    public function getMessages(int $chatId, array $messageIds): array
    {
        return $this->app->api()->messages->getMessages($chatId, $messageIds);
    }

    public function getMessage(int $chatId, int $messageId): ?Message
    {
        return $this->app->api()->messages->getMessage($chatId, $messageId);
    }

    public function forwardMessage(int $chatId, $messageId, ?int $sourceChatId = null, bool $notify = true): ?Message
    {
        return $this->app->api()->messages->forwardMessage($chatId, $messageId, $sourceChatId, $notify);
    }

    /**
     * @param array<int, mixed>|null $attachments
     */
    public function editMessage(int $chatId, int $messageId, string $text, ?array $attachments = null): Message
    {
        return $this->app->api()->messages->editMessage($chatId, $messageId, $text, $attachments);
    }

    /**
     * @return list<Message>|null
     */
    public function fetchHistory(
        int $chatId,
        int $forward = 0,
        int $backward = 40,
        int $backwardTime = 0,
        int $forwardTime = 0,
        ?int $fromTime = null,
        string $itemType = ItemType::REGULAR,
        bool $getChat = false,
        bool $getMessages = true,
        bool $interactive = false
    ): ?array {
        return $this->app->api()->messages->fetchHistory(
            $chatId,
            $forward,
            $backward,
            $backwardTime,
            $forwardTime,
            $fromTime,
            $itemType,
            $getChat,
            $getMessages,
            $interactive
        );
    }

    /**
     * @param list<int> $messageIds
     */
    public function deleteMessage(int $chatId, array $messageIds, bool $forMe): bool
    {
        return $this->app->api()->messages->deleteMessage($chatId, $messageIds, $forMe);
    }

    public function pinMessage(int $chatId, int $messageId, bool $notifyPin): bool
    {
        return $this->app->api()->messages->pinMessage($chatId, $messageId, $notifyPin);
    }

    public function addReaction(int $chatId, string $messageId, string $reaction): ?ReactionInfo
    {
        return $this->app->api()->messages->addReaction($chatId, $messageId, $reaction);
    }

    /**
     * @param list<string> $messageIds
     * @return array<string, ReactionInfo>|null
     */
    public function getReactions(int $chatId, array $messageIds): ?array
    {
        return $this->app->api()->messages->getReactions($chatId, $messageIds);
    }

    public function removeReaction(int $chatId, string $messageId): ?ReactionInfo
    {
        return $this->app->api()->messages->removeReaction($chatId, $messageId);
    }

    public function readMessage($messageId, int $chatId): ReadState
    {
        return $this->app->api()->messages->readMessage($messageId, $chatId);
    }

    public function getVideoById(int $chatId, $messageId, int $videoId): ?VideoRequest
    {
        return $this->app->api()->messages->getVideoById($chatId, $messageId, $videoId);
    }

    public function getFileById(int $chatId, $messageId, int $fileId): ?FileRequest
    {
        return $this->app->api()->messages->getFileById($chatId, $messageId, $fileId);
    }

    public function getBotInitData(int $botId, ?int $chatId = null, ?string $startParam = null): InitData
    {
        return $this->app->api()->bots->getInitData($botId, $chatId, $startParam);
    }

    /**
     * @param list<TelemetryEvent|array<string, mixed>> $events
     */
    public function sendTelemetryEvents(array $events): bool
    {
        return $this->app->api()->telemetry->sendEvents($events);
    }

    public function sendTelemetryLogin(?int $userId = null, ?int $sessionId = null): bool
    {
        $resolvedUserId = $userId;
        if ($resolvedUserId === null && $this->loginResponse !== null) {
            $resolvedUserId = $this->userIdFromLoginResponse($this->loginResponse);
        }

        if ($resolvedUserId === null) {
            return false;
        }

        return $this->app->api()->telemetry->login($resolvedUserId, $sessionId);
    }

    /**
     * @param list<Chat> $chats
     */
    public function sendTelemetryNavigationSession(
        ?int $userId = null,
        ?int $sessionId = null,
        ?RouteProfile $profile = null,
        array $chats = []
    ): bool {
        $resolvedUserId = $userId;
        if ($resolvedUserId === null && $this->loginResponse !== null) {
            $resolvedUserId = $this->userIdFromLoginResponse($this->loginResponse);
        }

        if ($resolvedUserId === null) {
            return false;
        }

        return $this->app->api()->telemetry->sendPlannedNavigation($resolvedUserId, $sessionId, $profile, $chats);
    }

    public function authorizeQrLogin(string $qrLink): bool
    {
        return $this->app->api()->auth->authorizeQrLogin($qrLink);
    }

    public function setTwoFactor(
        string $password,
        ?string $email = null,
        ?string $hint = null,
        ?EmailCodeProviderInterface $emailCodeProvider = null
    ): bool {
        return $this->app->api()->auth->setTwoFactor($password, $email, $hint, $emailCodeProvider);
    }

    public function removeTwoFactor(string $password): bool
    {
        return $this->app->api()->auth->removeTwoFactor($password);
    }

    public function changePassword(string $passwordOld, string $passwordNew): bool
    {
        return $this->app->api()->auth->changePassword($passwordOld, $passwordNew);
    }

    public function checkTwoFactor(): bool
    {
        return $this->app->api()->auth->checkTwoFactor();
    }

    /**
     * @param list<int>|null $participantIds
     * @return array{0: Chat, 1: Message}|null
     */
    public function createGroup(string $name, ?array $participantIds = null, bool $notify = true): ?array
    {
        return $this->app->api()->chats->createGroup($name, $participantIds, $notify);
    }

    /**
     * @param list<int> $userIds
     */
    public function inviteUsersToGroup(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        return $this->app->api()->chats->inviteUsersToGroup($chatId, $userIds, $showHistory);
    }

    /**
     * @param list<int> $userIds
     */
    public function inviteUsersToChannel(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        return $this->app->api()->chats->inviteUsersToChannel($chatId, $userIds, $showHistory);
    }

    /**
     * @param list<int> $userIds
     */
    public function removeUsersFromGroup(int $chatId, array $userIds, int $cleanMsgPeriod): bool
    {
        return $this->app->api()->chats->removeUsersFromGroup($chatId, $userIds, $cleanMsgPeriod);
    }

    public function changeGroupSettings(
        int $chatId,
        ?bool $allCanPinMessage = null,
        ?bool $onlyOwnerCanChangeIconTitle = null,
        ?bool $onlyAdminCanAddMember = null,
        ?bool $onlyAdminCanCall = null,
        ?bool $membersCanSeePrivateLink = null
    ): void {
        $this->app->api()->chats->changeGroupSettings(
            $chatId,
            $allCanPinMessage,
            $onlyOwnerCanChangeIconTitle,
            $onlyAdminCanAddMember,
            $onlyAdminCanCall,
            $membersCanSeePrivateLink
        );
    }

    public function changeGroupProfile(int $chatId, ?string $name, ?string $description = null): void
    {
        $this->app->api()->chats->changeGroupProfile($chatId, $name, $description);
    }

    public function joinGroup(string $link): Chat
    {
        return $this->app->api()->chats->joinGroup($link);
    }

    public function joinChannel(string $link): Chat
    {
        return $this->app->api()->chats->joinChannel($link);
    }

    public function resolveGroupByLink(string $link): ?Chat
    {
        return $this->app->api()->chats->resolveGroupByLink($link);
    }

    public function reworkInviteLink(int $chatId): Chat
    {
        return $this->app->api()->chats->reworkInviteLink($chatId);
    }

    /**
     * @param list<int> $chatIds
     * @return list<Chat>
     */
    public function getChats(array $chatIds): array
    {
        return $this->app->api()->chats->getChats($chatIds);
    }

    public function getChat(int $chatId): Chat
    {
        return $this->app->api()->chats->getChat($chatId);
    }

    public function leaveGroup(int $chatId): void
    {
        $this->app->api()->chats->leaveGroup($chatId);
    }

    public function leaveChannel(int $chatId): void
    {
        $this->app->api()->chats->leaveChannel($chatId);
    }

    /**
     * @return list<Chat>
     */
    public function fetchChats(?int $marker = null): array
    {
        return $this->app->api()->chats->fetchChats($marker);
    }

    /**
     * @return list<Member>
     */
    public function getJoinRequests(int $chatId, int $count = 100): array
    {
        return $this->app->api()->chats->getJoinRequests($chatId, $count);
    }

    /**
     * @param list<int> $userIds
     */
    public function confirmJoinRequests(int $chatId, array $userIds, bool $showHistory = true): ?Chat
    {
        return $this->app->api()->chats->confirmJoinRequests($chatId, $userIds, $showHistory);
    }

    public function confirmJoinRequest(int $chatId, int $userId, bool $showHistory = true): ?Chat
    {
        return $this->app->api()->chats->confirmJoinRequest($chatId, $userId, $showHistory);
    }

    /**
     * @param list<int> $userIds
     */
    public function declineJoinRequests(int $chatId, array $userIds): ?Chat
    {
        return $this->app->api()->chats->declineJoinRequests($chatId, $userIds);
    }

    public function declineJoinRequest(int $chatId, int $userId): ?Chat
    {
        return $this->app->api()->chats->declineJoinRequest($chatId, $userId);
    }

    public function deleteChat(int $chatId, ?int $lastEventTime = null, bool $forAll = true): void
    {
        $this->app->api()->chats->deleteChat($chatId, $lastEventTime, $forAll);
    }

    public function getCachedUser(int $userId): ?User
    {
        return $this->app->api()->users->getCachedUser($userId);
    }

    /**
     * @param list<int> $userIds
     * @return list<User>
     */
    public function getUsers(array $userIds): array
    {
        return $this->app->api()->users->getUsers($userIds);
    }

    public function getUser(int $userId): ?User
    {
        return $this->app->api()->users->getUser($userId);
    }

    /**
     * @param list<int> $userIds
     * @return list<User>
     */
    public function fetchUsers(array $userIds): array
    {
        return $this->app->api()->users->fetchUsers($userIds);
    }

    public function searchByPhone(string $phone): User
    {
        return $this->app->api()->users->searchByPhone($phone);
    }

    /**
     * @return list<Session>
     */
    public function getSessions(): array
    {
        return $this->app->api()->users->getSessions();
    }

    public function addContact(int $contactId): User
    {
        return $this->app->api()->users->addContact($contactId);
    }

    public function removeContact(int $contactId): bool
    {
        return $this->app->api()->users->removeContact($contactId);
    }

    /**
     * @param list<ContactInfo> $contacts
     * @return list<User>
     */
    public function importContacts(array $contacts): array
    {
        return $this->app->api()->users->importContacts($contacts);
    }

    public function getChatId(int $firstUserId, int $secondUserId): int
    {
        return $this->app->api()->users->getChatId($firstUserId, $secondUserId);
    }

    public function requestProfilePhotoUploadUrl(): string
    {
        return $this->app->api()->account->requestProfilePhotoUploadUrl();
    }

    public function changeProfile(
        string $firstName,
        ?string $lastName = null,
        ?string $description = null,
        $photo = null,
        ?string $photoToken = null
    ): bool {
        return $this->app->api()->account->changeProfile($firstName, $lastName, $description, $photo, $photoToken);
    }

    public function uploadPhoto(Photo $photo, bool $profile = false): AttachPhotoPayload
    {
        return $this->app->api()->uploads->uploadPhoto($photo, $profile);
    }

    public function uploadVideo(Video $video): VideoAttachPayload
    {
        return $this->app->api()->uploads->uploadVideo($video);
    }

    public function uploadFile(File $file): AttachFilePayload
    {
        return $this->app->api()->uploads->uploadFile($file);
    }

    /**
     * @param list<int> $chatInclude
     * @param list<mixed>|null $filters
     */
    public function createFolder(string $title, array $chatInclude, ?array $filters = null): FolderUpdate
    {
        return $this->app->api()->account->createFolder($title, $chatInclude, $filters);
    }

    public function getFolders(int $folderSync = 0): FolderList
    {
        return $this->app->api()->account->getFolders($folderSync);
    }

    /**
     * @param list<int>|null $chatInclude
     * @param list<mixed>|null $filters
     * @param list<mixed>|null $options
     */
    public function updateFolder(
        string $folderId,
        string $title,
        ?array $chatInclude = null,
        ?array $filters = null,
        ?array $options = null
    ): FolderUpdate {
        return $this->app->api()->account->updateFolder($folderId, $title, $chatInclude, $filters, $options);
    }

    public function deleteFolder(string $folderId): FolderUpdate
    {
        return $this->app->api()->account->deleteFolder($folderId);
    }

    public function closeAllSessions(): bool
    {
        return $this->app->api()->account->closeAllSessions();
    }

    public function logout(): bool
    {
        return $this->app->api()->account->logout();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function connection(): ConnectionManager
    {
        return $this->connection;
    }

    public function app(): App
    {
        return $this->app;
    }

    public function loginResponse(): ?LoginResponse
    {
        return $this->loginResponse;
    }

    private function authenticateIfConfigured(bool $force = false): void
    {
        if ($this->loginResponse !== null && !$force) {
            return;
        }

        $session = $this->app->store()->loadSession();
        $hasAuthInput = $session !== null || $this->options->token !== null || $this->options->authFlow !== null;
        if (!$hasAuthInput) {
            return;
        }

        $deviceId = $session !== null ? $session->deviceId : $this->options->deviceId;
        if ($session !== null && $session->mtInstanceId !== null && $session->mtInstanceId !== '') {
            $this->options->mtInstanceId = $session->mtInstanceId;
        }
        $this->app->api()->session->handshake($this->options->mtInstanceId, $this->options->userAgent, $deviceId);

        if ($session === null) {
            if ($this->options->token !== null) {
                $session = new SessionInfo([
                    'token' => $this->options->token,
                    'deviceId' => $this->options->deviceId,
                    'phone' => $this->options->phone !== null ? $this->options->phone : '',
                    'mtInstanceId' => $this->options->mtInstanceId,
                ]);
            } elseif ($this->options->authFlow !== null) {
                $result = $this->options->authFlow->authenticate($this->app->api()->auth, $this->options);
                if ($result->token === null || $result->token === '') {
                    throw new PHPMaxException('Authentication failed: no token received');
                }
                $session = new SessionInfo([
                    'token' => $result->token,
                    'deviceId' => $this->options->deviceId,
                    'phone' => $this->options->phone !== null ? $this->options->phone : '',
                    'mtInstanceId' => $this->options->mtInstanceId,
                ]);
            }

            if ($session !== null) {
                $this->app->store()->saveSession($session);
            }
        }

        if ($session === null) {
            return;
        }

        $this->app->setSession($session);
        $login = $this->app->api()->auth->login($this->options->userAgent);

        $current = $this->app->session();
        if ($current !== null && $login->token !== null && $login->token !== $current->token) {
            $this->app->store()->updateToken($current->token, $login->token);
            $current = new SessionInfo([
                'token' => $login->token,
                'deviceId' => $current->deviceId,
                'phone' => $current->phone,
                'mtInstanceId' => $current->mtInstanceId,
                'sync' => $current->sync,
            ]);
            $this->app->setSession($current);
        }

        $this->loginResponse = $this->cacheLoginState($login);
        $this->sendLoginTelemetry($this->loginResponse);
    }

    private function cacheLoginState(LoginResponse $login): LoginResponse
    {
        return $this->app->applyLoginState($login);
    }

    private function handleRunDisconnect(ProtocolException $exception, ExecutionBudget $budget): bool
    {
        $this->close();

        if (!$this->options->reconnect) {
            $this->emitDisconnect($exception, false, 0.0);

            return false;
        }

        $delay = $this->options->reconnectDelay;
        $this->emitDisconnect($exception, true, $delay);

        if ($delay > 0.0) {
            $sleep = min($delay, $budget->remaining());
            if ($sleep > 0.0) {
                usleep((int) floor($sleep * 1000000));
            }
        }

        if ($budget->expired()) {
            return true;
        }

        $this->open();

        return true;
    }

    private function nextPingDeadline(): ?float
    {
        if ($this->options->pingInterval <= 0.0) {
            return null;
        }

        return microtime(true) + $this->options->pingInterval;
    }

    private function runReadTimeout(ExecutionBudget $budget, ?float $nextPingAt): float
    {
        $timeout = min(max(0.001, $this->options->requestTimeout), max(0.001, $budget->remaining()));
        if ($nextPingAt !== null) {
            $timeout = min($timeout, max(0.001, $nextPingAt - microtime(true)));
        }

        return $timeout;
    }

    private function runPingIfDue(?float $nextPingAt, ExecutionBudget $budget, ?float $startedAt = null): ?float
    {
        if ($nextPingAt === null || microtime(true) < $nextPingAt || $budget->expired()) {
            return $nextPingAt;
        }

        $startedAt = $startedAt !== null ? $startedAt : microtime(true);
        $this->traceRuntime('ping_due', $startedAt, $budget, [
            'ping_interval_ms' => (int) round($this->options->pingInterval * 1000),
        ]);
        try {
            $this->app->invoke(
                Opcode::PING,
                ['interactive' => true],
                Command::REQUEST,
                min(max(0.001, $this->options->requestTimeout), max(0.001, $budget->remaining()))
            );
            $this->traceRuntime('ping_ok', $startedAt, $budget, []);
        } catch (ProtocolException $e) {
            $this->traceRuntime('ping_error', $startedAt, $budget, [
                'error' => $this->shortError($e),
                'timeout' => $this->isTimeoutException($e) ? 1 : 0,
            ]);
            if (!$this->isTimeoutException($e) && !$this->handleRunDisconnect($e, $budget)) {
                $this->traceRuntime('ping_error_unhandled', $startedAt, $budget, [
                    'error' => $this->shortError($e),
                ]);
                throw $e;
            }
            $this->traceRuntime('ping_error_reconnected', $startedAt, $budget, [
                'connection_open' => $this->connection->isOpen() ? 1 : 0,
            ]);
        }

        return $this->nextPingDeadline();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function traceRuntime(string $event, float $startedAt, ExecutionBudget $budget, array $context): void
    {
        if (!is_callable($this->options->debugLogger)) {
            return;
        }

        $safe = [
            'event' => $event,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'remaining_ms' => (int) round(max(0.0, $budget->remaining()) * 1000),
        ];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        call_user_func($this->options->debugLogger, $event, $safe);
    }

    private function millisecondsUntil(?float $deadline): int
    {
        if ($deadline === null) {
            return -1;
        }
        return (int) round(max(0.0, $deadline - microtime(true)) * 1000);
    }

    private function shortError(ProtocolException $exception): string
    {
        return substr(str_replace(["\r", "\n"], ' ', $exception->getMessage()), 0, 180);
    }

    /**
     * @param array<mixed>|null $payload
     */
    private function payloadKeySummary(?array $payload): string
    {
        if ($payload === null || $payload === []) {
            return '';
        }
        $keys = array_slice(array_map('strval', array_keys($payload)), 0, 12);
        sort($keys);
        return implode(',', $keys);
    }

    private function isTimeoutException(ProtocolException $exception): bool
    {
        return stripos($exception->getMessage(), 'timed out') !== false;
    }

    private function sendLoginTelemetry(LoginResponse $login): void
    {
        if (!$this->options->telemetry) {
            return;
        }

        $userId = $this->userIdFromLoginResponse($login);
        if ($userId === null) {
            return;
        }

        try {
            $this->app->api()->telemetry->login($userId, $this->options->clientSessionId);
        } catch (Throwable $e) {
            return;
        }
    }

    private function userIdFromLoginResponse(LoginResponse $login): ?int
    {
        if ($login->profile === null || $login->profile->contact === null || $login->profile->contact->id === null) {
            return null;
        }

        return $login->profile->contact->id;
    }
}
