<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

use PHPMax\Api\ApiFacade;
use PHPMax\Api\Binding;
use PHPMax\Config\ClientOptions;
use PHPMax\Dispatch\Router;
use PHPMax\Domain\Chat;
use PHPMax\Domain\LoginResponse;
use PHPMax\Domain\MaxApiError;
use PHPMax\Domain\Message;
use PHPMax\Domain\Profile;
use PHPMax\Domain\User;
use PHPMax\Exception\ApiException;
use PHPMax\Exception\ValidationException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Session\JsonFileSessionStore;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;

class App
{
    /** @var ConnectionManager */
    private $connection;
    /** @var ClientOptions */
    private $options;
    /** @var SessionStoreInterface */
    private $store;
    /** @var ApiFacade */
    private $api;
    /** @var Router */
    private $internalRouter;
    /** @var SessionInfo|null */
    private $session;
    /** @var Profile|null */
    private $profile;
    /** @var list<Chat>|null */
    private $chats;
    /** @var array<int, User> */
    private $users;
    /** @var list<User|null> */
    private $contacts;
    /** @var array<int|string, list<Message>> */
    private $messages;
    /** @var float */
    private $requestTimeout;

    /**
     * @param ClientOptions|float|null $options
     */
    public function __construct(ConnectionManager $connection, $options = null, ?SessionStoreInterface $store = null)
    {
        $this->connection = $connection;
        if ($options instanceof ClientOptions) {
            $this->options = $options;
        } elseif (is_float($options) || is_int($options)) {
            $this->options = new ClientOptions(['requestTimeout' => (float) $options]);
        } else {
            $this->options = new ClientOptions();
        }
        $this->requestTimeout = $this->options->requestTimeout;
        $this->store = $store ?: ($this->options->store ?: new JsonFileSessionStore($this->options->workDir, $this->options->sessionName));
        $this->session = null;
        $this->profile = null;
        $this->chats = null;
        $this->users = [];
        $this->contacts = [];
        $this->messages = [];
        $this->internalRouter = new Router();
        $this->connection->addEventListener([$this, 'dispatchInternalFrame']);
        $this->api = new ApiFacade($this);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function invoke(int $opcode, array $payload, int $cmd = Command::REQUEST, ?float $timeout = null): InboundFrame
    {
        $seq = $this->connection->nextSeq();
        $frame = new OutboundFrame($this->connection->protocolVersion(), $opcode, $seq, $payload, $cmd);
        $response = $this->connection->request($frame, $timeout !== null ? $timeout : $this->requestTimeout);

        if ($response->cmd === Command::ERROR) {
            throw $this->buildApiError($response);
        }

        return $response;
    }

    public function options(): ClientOptions
    {
        return $this->options;
    }

    public function store(): SessionStoreInterface
    {
        return $this->store;
    }

    public function api(): ApiFacade
    {
        return $this->api;
    }

    public function onInternal(string $eventType, callable $handler, callable ...$filters): void
    {
        $this->internalRouter->on($eventType, $handler, ...$filters);
    }

    public function dispatchInternalFrame(InboundFrame $frame): void
    {
        $this->internalRouter->dispatchFrame($frame, $this, false);
    }

    public function session(): ?SessionInfo
    {
        return $this->session;
    }

    public function setSession(?SessionInfo $session): void
    {
        $this->session = $session;
    }

    public function close(): void
    {
        $this->connection->close();
        $this->store->close();
    }

    public function applyLoginState(LoginResponse $login): LoginResponse
    {
        $login = Binding::bindApiModel($this, $login);
        $this->profile = $login->profile;
        $this->chats = [];
        $this->contacts = $login->contacts;
        $this->messages = Binding::bindApiModel($this, $login->messages);

        foreach ($login->chats as $chat) {
            if ($chat instanceof Chat) {
                $this->cacheChat($chat);
            }
        }

        if ($login->profile !== null && $login->profile->contact !== null) {
            $this->cacheUser($login->profile->contact);
        }

        foreach ($login->contacts as $contact) {
            if ($contact instanceof User) {
                $this->cacheUser($contact);
            }
        }

        return $login;
    }

    public function clearState(): void
    {
        $this->profile = null;
        $this->chats = null;
        $this->users = [];
        $this->contacts = [];
        $this->messages = [];
    }

    public function me(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): Profile
    {
        $profile = Binding::bindApiModel($this, $profile);
        $this->profile = $profile;
        if ($profile->contact !== null) {
            $this->cacheUser($profile->contact);
        }

        return $profile;
    }

    /**
     * @return list<Chat>|null
     */
    public function chats(): ?array
    {
        return $this->chats;
    }

    /**
     * @return list<User|null>
     */
    public function contacts(): array
    {
        return $this->contacts;
    }

    /**
     * @return array<int|string, list<Message>>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function cachedChat(int $chatId): ?Chat
    {
        foreach ($this->chats !== null ? $this->chats : [] as $chat) {
            if ($chat->id === $chatId) {
                return $chat;
            }
        }

        return null;
    }

    public function cacheChat(Chat $chat): Chat
    {
        $chat = Binding::bindApiModel($this, $chat);
        if ($chat->id === null) {
            return $chat;
        }

        if ($this->chats === null) {
            $this->chats = [$chat];
            return $chat;
        }

        foreach ($this->chats as $index => $cached) {
            if ($cached->id === $chat->id) {
                $this->chats[$index] = $chat;
                return $chat;
            }
        }

        $this->chats[] = $chat;

        return $chat;
    }

    public function removeCachedChat(int $chatId): void
    {
        if ($this->chats === null) {
            return;
        }

        $this->chats = array_values(array_filter($this->chats, static function (Chat $chat) use ($chatId): bool {
            return $chat->id !== $chatId;
        }));
    }

    public function cachedUser(int $userId): ?User
    {
        return $this->users[$userId] ?? null;
    }

    public function cacheUser(User $user): User
    {
        $user = Binding::bindApiModel($this, $user);
        if ($user->id !== null) {
            $this->users[$user->id] = $user;
        }

        return $user;
    }

    public function removeCachedUser(int $userId): void
    {
        unset($this->users[$userId]);
    }

    public function connection(): ConnectionManager
    {
        return $this->connection;
    }

    private function buildApiError(InboundFrame $response): ApiException
    {
        $payload = is_array($response->payload) ? $response->payload : [];

        try {
            $error = MaxApiError::fromArray($payload);

            return new ApiException(
                $response->opcode,
                $error->error,
                $error->title,
                $error->message,
                $error->localizedMessage,
                $payload
            );
        } catch (ValidationException $e) {
            return new ApiException(
                $response->opcode,
                'unknown_error',
                'Unknown error',
                $e->getMessage(),
                $e->getMessage(),
                $payload
            );
        }
    }
}
