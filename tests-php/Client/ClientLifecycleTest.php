<?php

declare(strict_types=1);

use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Dispatch\Router;
use PHPMax\Domain\SyncState;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Exception\ProtocolException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;
use PHPMax\Transport\TransportInterface;

final class FakeClientTransport implements TransportInterface
{
    /** @var list<string> */
    private $chunks;
    /** @var bool */
    private $connected = false;
    /** @var list<string> */
    public $sent = [];

    /**
     * @param list<string> $chunks
     */
    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function recv(int $length, float $timeout): string
    {
        $chunk = array_shift($this->chunks);
        if ($chunk === null) {
            throw new RuntimeException('No fake chunks left');
        }
        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class InMemoryClientStore implements SessionStoreInterface
{
    /** @var SessionInfo|null */
    public $session;
    /** @var list<SessionInfo> */
    public $saved = [];
    /** @var list<array{0: string, 1: string}> */
    public $updated = [];
    /** @var list<string> */
    public $deleted = [];
    /** @var int */
    public $closes = 0;

    public function __construct(?SessionInfo $session = null)
    {
        $this->session = $session;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
        $this->session = $sessionInfo;
        $this->saved[] = $sessionInfo;
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
        $this->updated[] = [$oldToken, $newToken];
        if ($this->session !== null && $this->session->token === $oldToken) {
            $this->session = new SessionInfo([
                'token' => $newToken,
                'deviceId' => $this->session->deviceId,
                'phone' => $this->session->phone,
                'mtInstanceId' => $this->session->mtInstanceId,
                'sync' => $this->session->sync,
            ]);
        }
    }

    public function loadSession(): ?SessionInfo
    {
        return $this->session;
    }

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo
    {
        return $this->session !== null && $this->session->deviceId === $deviceId ? $this->session : null;
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        return $this->session !== null && $this->session->phone === $phone ? $this->session : null;
    }

    public function deleteSession(string $token): void
    {
        $this->deleted[] = $token;
        if ($this->session !== null && $this->session->token === $token) {
            $this->session = null;
        }
    }

    public function deleteAllSessions(): void
    {
        $this->deleted[] = '*';
        $this->session = null;
    }

    public function close(): void
    {
        $this->closes++;
    }
}

final class ReconnectClientTransport implements TransportInterface
{
    /** @var string */
    private $header;
    /** @var string */
    private $payload;
    /** @var bool */
    private $connected = false;
    /** @var int */
    public $connects = 0;
    /** @var int */
    public $closes = 0;
    /** @var int */
    private $recvCount = 0;

    public function __construct(string $rawFrame)
    {
        $this->header = substr($rawFrame, 0, 10);
        $this->payload = substr($rawFrame, 10);
    }

    public function connect(): void
    {
        $this->connects++;
        $this->connected = true;
    }

    public function close(): void
    {
        $this->closes++;
        $this->connected = false;
    }

    public function send(string $data): void
    {
    }

    public function recv(int $length, float $timeout): string
    {
        if ($this->connects === 1) {
            throw new ProtocolException('TCP transport closed by peer');
        }

        $this->recvCount++;
        if ($this->recvCount === 1) {
            return $this->header;
        }
        if ($this->recvCount === 2) {
            $this->connected = false;
            return $this->payload;
        }

        throw new ProtocolException('Timed out reading from TCP transport');
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class PingClientTransport implements TransportInterface
{
    /** @var TcpProtocol */
    private $protocol;
    /** @var bool */
    private $connected = false;
    /** @var list<string> */
    private $chunks = [];
    /** @var bool */
    private $closeWhenChunksDrained;
    /** @var bool */
    private $closeOnIdleTimeout;
    /** @var list<string> */
    public $sent = [];

    public function __construct(TcpProtocol $protocol, bool $closeOnIdleTimeout = false)
    {
        $this->protocol = $protocol;
        $this->closeWhenChunksDrained = false;
        $this->closeOnIdleTimeout = $closeOnIdleTimeout;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
        $frame = $this->protocol->decode($data);
        if ($frame->opcode !== Opcode::PING) {
            return;
        }

        $responseRaw = $this->protocol->encode(new OutboundFrame(
            TcpProtocol::VERSION,
            Opcode::PING,
            $frame->seq !== null ? $frame->seq : 0,
            ['ok' => true],
            Command::RESPONSE
        ));
        $this->chunks = [substr($responseRaw, 0, 10), substr($responseRaw, 10)];
        $this->closeWhenChunksDrained = true;
    }

    public function recv(int $length, float $timeout): string
    {
        $chunk = array_shift($this->chunks);
        if ($chunk === null) {
            if ($this->closeOnIdleTimeout) {
                $this->connected = false;
            }
            throw new ProtocolException('Timed out reading from TCP transport');
        }

        if ($this->closeWhenChunksDrained && count($this->chunks) === 0) {
            $this->connected = false;
        }

        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $assertSame(30.0, (new ClientOptions())->pingInterval);
    $assertSame(0.0, (new ClientOptions(['pingInterval' => -1.0]))->pingInterval);
    $boundedOptions = new ClientOptions([
        'requestTimeout' => -1.0,
        'connectTimeout' => 0.0,
        'executionSafetyMargin' => -10.0,
    ]);
    $assertSame(0.001, $boundedOptions->requestTimeout);
    $assertSame(0.001, $boundedOptions->connectTimeout);
    $assertSame(0.0, $boundedOptions->executionSafetyMargin);

    $responseRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::PING,
        0,
        ['ok' => true],
        Command::RESPONSE
    ));
    $transport = new FakeClientTransport([
        substr($responseRaw, 0, 10),
        substr($responseRaw, 10),
    ]);
    $manager = new ConnectionManager($transport, $protocol);
    $starts = 0;
    $router = new Router();
    $router->onStart(static function (Client $client) use (&$starts): void {
        $starts++;
    });

    $client = new Client(new ClientOptions(['requestTimeout' => 1.0]), $manager, $router);
    $client->open();
    $assertSame(1, $starts);
    $assert($client->connection()->isOpen(), 'Client must open injected connection');

    $response = $client->invoke(Opcode::PING, ['interactive' => true]);
    $assertSame(['ok' => true], $response->payload);
    $assertSame(1, count($transport->sent));

    $client->close();
    $assert(!$client->connection()->isOpen(), 'Client must close injected connection');

    $closeStore = new InMemoryClientStore();
    $closeTransport = new FakeClientTransport([]);
    $closeClient = new Client(new ClientOptions(['store' => $closeStore]), new ConnectionManager($closeTransport, $protocol));
    $closeClient->open();
    $closeClient->close();
    $assertSame(1, $closeStore->closes, 'Client::close must close configured session store');
    $assert(!$closeClient->connection()->isOpen(), 'Client::close must close connection through App lifecycle');

    $startFailureStore = new InMemoryClientStore();
    $startFailureTransport = new FakeClientTransport([]);
    $startFailureRouter = new Router();
    $startFailureRouter->onStart(static function (): void {
        throw new RuntimeException('startup boom');
    });
    $startFailureClient = new Client(
        new ClientOptions(['store' => $startFailureStore]),
        new ConnectionManager($startFailureTransport, $protocol),
        $startFailureRouter
    );
    $assertThrows(RuntimeException::class, static function () use ($startFailureClient): void {
        $startFailureClient->open();
    }, 'Unhandled onStart failure must propagate after cleanup');
    $assert(!$startFailureClient->connection()->isOpen(), 'open() must close connection after unhandled onStart failure');
    $assertSame(1, $startFailureStore->closes, 'open() must close session store after unhandled onStart failure');

    $calls = [];
    $router2 = new Router();
    $router2->onRaw(
        static function (InboundFrame $frame, Client $client) use (&$calls): void {
            $calls[] = $frame->opcode;
        },
        static function (InboundFrame $frame): bool {
            return $frame->opcode === Opcode::PING;
        }
    );
    $client2 = new Client(new ClientOptions(), new ConnectionManager(new FakeClientTransport([]), $protocol), $router2);
    $router2->dispatchRaw(new InboundFrame(Opcode::NOTIF_TYPING), $client2);
    $router2->dispatchRaw(new InboundFrame(Opcode::PING), $client2);
    $assertSame([Opcode::PING], $calls);

    $openCloseTransport = new FakeClientTransport([]);
    $openCloseStore = new InMemoryClientStore();
    $client3 = new Client(new ClientOptions(['store' => $openCloseStore]), new ConnectionManager($openCloseTransport, $protocol));
    $result = $client3->withOpenSession(static function (Client $client): string {
        return $client->connection()->isOpen() ? 'open' : 'closed';
    });
    $assertSame('open', $result);
    $assert(!$client3->connection()->isOpen(), 'withOpenSession must close after callback');
    $assertSame(1, $openCloseStore->closes, 'withOpenSession must close configured session store');

    $handshakeRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::SESSION_INIT,
        0,
        [],
        Command::RESPONSE
    ));
    $loginRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::LOGIN,
        1,
        [
            'profile' => ['contact' => ['id' => 77, 'names' => [['name' => 'Me', 'type' => 'FIRST']]]],
            'chats' => [
                ['id' => 700, 'type' => 'CHAT', 'status' => 'ACTIVE', 'owner' => 77, 'title' => 'Team'],
            ],
            'messages' => [
                700 => [
                    ['id' => 900, 'chatId' => 700, 'time' => 100, 'type' => 'USER', 'text' => 'from login'],
                ],
            ],
            'contacts' => [
                ['id' => 88, 'names' => [['name' => 'Friend', 'type' => 'FIRST']]],
            ],
            'token' => 'server-token',
            'time' => 999,
            'config' => ['hash' => 'cfg'],
        ],
        Command::RESPONSE
    ));
    $replyRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::MSG_SEND,
        2,
        ['id' => 901, 'chatId' => 700, 'time' => 101, 'type' => 'USER', 'text' => 'reply'],
        Command::RESPONSE
    ));
    $tokenTransport = new FakeClientTransport([
        substr($handshakeRaw, 0, 10),
        substr($handshakeRaw, 10),
        substr($loginRaw, 0, 10),
        substr($loginRaw, 10),
        substr($replyRaw, 0, 10),
        substr($replyRaw, 10),
    ]);
    $tokenStore = new InMemoryClientStore();
    $tokenClient = new Client(new ClientOptions([
        'token' => 'local-token',
        'phone' => '+79990000000',
        'deviceId' => 'device-token',
        'mtInstanceId' => 'mt-token',
        'store' => $tokenStore,
        'requestTimeout' => 1.0,
    ]), new ConnectionManager($tokenTransport, $protocol));
    $tokenClient->open();
    $assertSame('server-token', $tokenClient->loginResponse()->token);
    $assertSame('server-token', $tokenClient->app()->session()->token);
    $assertSame([['local-token', 'server-token']], $tokenStore->updated);
    $assertSame(2, count($tokenTransport->sent));
    $assertSame(Opcode::SESSION_INIT, $protocol->decode($tokenTransport->sent[0])->opcode);
    $assertSame('mt-token', $protocol->decode($tokenTransport->sent[0])->payload['mt_instanceid']);
    $assertSame('device-token', $protocol->decode($tokenTransport->sent[0])->payload['deviceId']);
    $assertSame(Opcode::LOGIN, $protocol->decode($tokenTransport->sent[1])->opcode);
    $assertSame(77, $tokenClient->me()->contact->id);
    $assertSame(700, $tokenClient->chats()[0]->id);
    $assertSame(88, $tokenClient->contacts()[0]->id);
    $assertSame(900, $tokenClient->messages()[700][0]->id);
    $assertSame(77, $tokenClient->app()->me()->contact->id);
    $assertSame(700, $tokenClient->app()->chats()[0]->id);
    $assertSame(88, $tokenClient->app()->contacts()[0]->id);
    $assertSame(900, $tokenClient->app()->messages()[700][0]->id);
    $assertSame(700, $tokenClient->app()->cachedChat(700)->id);
    $assertSame(88, $tokenClient->app()->cachedUser(88)->id);
    $assertSame(700, $tokenClient->getChat(700)->id, 'Login chats must seed chat cache');
    $assertSame(88, $tokenClient->getCachedUser(88)->id, 'Login contacts must seed user cache');
    $reply = $tokenClient->messages()[700][0]->reply('reply', null, false);
    $assertSame(901, $reply->id, 'Login messages must be bound to message service');
    $assertSame(Opcode::MSG_SEND, $protocol->decode($tokenTransport->sent[2])->opcode);
    $assertSame(['type' => 'REPLY', 'messageId' => 900], $protocol->decode($tokenTransport->sent[2])->payload['message']['link']);
    $tokenClient->relogin(true, false);
    $assertSame(1, $tokenStore->closes, 'relogin must close session store through App lifecycle');
    $assertSame(['server-token'], $tokenStore->deleted);
    $assertSame(null, $tokenClient->loginResponse());
    $assertSame(null, $tokenClient->me());
    $assertSame(null, $tokenClient->app()->me());
    $assertSame(null, $tokenClient->app()->chats());
    $assertSame([], $tokenClient->contacts());
    $assertSame([], $tokenClient->messages());
    $assertSame([], $tokenClient->app()->contacts());
    $assertSame([], $tokenClient->app()->messages());
    $assertSame(null, $tokenClient->app()->cachedChat(700));
    $assertSame(null, $tokenClient->app()->cachedUser(88));
    $assertSame(null, $tokenClient->app()->session());
    $assertSame(null, $tokenClient->app()->options()->token);

    $savedLoginRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::LOGIN,
        1,
        [
            'profile' => ['contact' => ['id' => 79, 'names' => []]],
            'chats' => [],
            'messages' => [],
            'contacts' => [],
        ],
        Command::RESPONSE
    ));
    $savedSessionStore = new InMemoryClientStore(new SessionInfo([
        'token' => 'saved-token',
        'deviceId' => 'saved-device',
        'phone' => '+79990000001',
        'mtInstanceId' => 'saved-mt',
        'sync' => new SyncState(['chatsSync' => 10]),
    ]));
    $savedSessionTransport = new FakeClientTransport([
        substr($handshakeRaw, 0, 10),
        substr($handshakeRaw, 10),
        substr($savedLoginRaw, 0, 10),
        substr($savedLoginRaw, 10),
    ]);
    $savedSessionClient = new Client(new ClientOptions([
        'token' => 'config-token-must-not-win',
        'deviceId' => 'config-device',
        'mtInstanceId' => 'config-mt',
        'store' => $savedSessionStore,
        'requestTimeout' => 1.0,
    ]), new ConnectionManager($savedSessionTransport, $protocol));
    $savedSessionClient->open();
    $savedHandshake = $protocol->decode($savedSessionTransport->sent[0]);
    $assertSame(Opcode::SESSION_INIT, $savedHandshake->opcode);
    $assertSame('saved-mt', $savedHandshake->payload['mt_instanceid'], 'Saved session mt_instance_id must be reused for handshake like PyMax');
    $assertSame('saved-device', $savedHandshake->payload['deviceId'], 'Saved session device id must be reused for handshake');
    $assertSame('saved-mt', $savedSessionClient->app()->options()->mtInstanceId);
    $assertSame('saved-mt', $savedSessionClient->app()->session()->mtInstanceId);
    $assertSame('saved-token', $protocol->decode($savedSessionTransport->sent[1])->payload['token']);
    $assertSame([], $savedSessionStore->updated);

    $neverLoggedClient = new Client(new ClientOptions(), new ConnectionManager(new FakeClientTransport([]), $protocol));
    $assertThrows(PHPMaxException::class, static function () use ($neverLoggedClient): void {
        $neverLoggedClient->relogin(false, false);
    });

    $eventRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::NOTIF_TYPING,
        22,
        ['chatId' => 900, 'userId' => 901],
        Command::REQUEST
    ));
    $reconnectTransport = new ReconnectClientTransport($eventRaw);
    $reconnectManager = new ConnectionManager($reconnectTransport, $protocol);
    $reconnectRouter = new Router();
    $reconnectStarts = 0;
    $reconnectDisconnects = [];
    $reconnectEvents = [];
    $reconnectRouter->onStart(static function () use (&$reconnectStarts): void {
        $reconnectStarts++;
    });
    $reconnectRouter->onDisconnect(static function (Throwable $exception, bool $reconnect, float $delay) use (&$reconnectDisconnects): void {
        $reconnectDisconnects[] = [$exception->getMessage(), $reconnect, $delay];
    });
    $reconnectRouter->onRaw(static function (InboundFrame $frame) use (&$reconnectEvents): void {
        $reconnectEvents[] = $frame->opcode;
    });
    $reconnectClient = new Client(new ClientOptions([
        'requestTimeout' => 1.0,
        'executionSafetyMargin' => 0.0,
        'reconnectDelay' => 0.0,
    ]), $reconnectManager, $reconnectRouter);
    $reconnectClient->open();
    $reconnectClient->runFor(1);
    $assertSame(2, $reconnectTransport->connects, 'runFor must reconnect after non-timeout protocol failure');
    $assertSame(2, $reconnectStarts, 'onStart must be emitted after initial open and reconnect');
    $assertSame([['TCP transport closed by peer', true, 0.0]], $reconnectDisconnects);
    $assertSame([Opcode::NOTIF_TYPING], $reconnectEvents);

    $pingTransport = new PingClientTransport($protocol);
    $pingClient = new Client(new ClientOptions([
        'requestTimeout' => 0.01,
        'executionSafetyMargin' => 0.0,
        'pingInterval' => 0.001,
    ]), new ConnectionManager($pingTransport, $protocol));
    $pingClient->open();
    $pingClient->runFor(1);
    $assertSame(1, count($pingTransport->sent), 'runFor must send heartbeat ping when idle deadline is reached');
    $pingFrame = $protocol->decode($pingTransport->sent[0]);
    $assertSame(Opcode::PING, $pingFrame->opcode);
    $assertSame(['interactive' => true], $pingFrame->payload);

    $disabledPingTransport = new PingClientTransport($protocol, true);
    $disabledPingClient = new Client(new ClientOptions([
        'requestTimeout' => 0.01,
        'executionSafetyMargin' => 0.0,
        'pingInterval' => 0.0,
    ]), new ConnectionManager($disabledPingTransport, $protocol));
    $disabledPingClient->open();
    $disabledPingClient->runFor(1);
    $assertSame(0, count($disabledPingTransport->sent), 'pingInterval=0.0 must disable runFor heartbeat');
};
