<?php

declare(strict_types=1);

use PHPMax\Api\Telemetry\TelemetryPayloadBuilder;
use PHPMax\Api\Telemetry\NavigationPlanner;
use PHPMax\Api\Telemetry\NavigationRules;
use PHPMax\Api\Telemetry\RouteProfile;
use PHPMax\Api\Telemetry\Screen;
use PHPMax\Api\Telemetry\ScreenTransition;
use PHPMax\Api\Telemetry\TelemetryService;
use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Domain\Chat;
use PHPMax\Domain\ChatType;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;
use PHPMax\Transport\TransportInterface;

final class TelemetryTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake telemetry chunks left');
        }
        if (strlen($chunk) !== $length) {
            throw new RuntimeException('Expected chunk length ' . $length . ', got ' . strlen($chunk));
        }

        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class TelemetryTestStore implements SessionStoreInterface
{
    /** @var SessionInfo|null */
    public $session;

    public function __construct(?SessionInfo $session = null)
    {
        $this->session = $session;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
        $this->session = $sessionInfo;
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
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
        if ($this->session !== null && $this->session->token === $token) {
            $this->session = null;
        }
    }

    public function deleteAllSessions(): void
    {
        $this->session = null;
    }

    public function close(): void
    {
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $frameChunks = static function (int $opcode, int $seq, array $payload = []) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(
            TcpProtocol::VERSION,
            $opcode,
            $seq,
            $payload,
            Command::RESPONSE
        ));

        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (TelemetryTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $builder = new TelemetryPayloadBuilder();
    $login = $builder->login(100, 44)->toArray();
    $assertSame('PERF', $login['type']);
    $assertSame('login', $login['event']);
    $assertSame(100, $login['userId']);
    $assertSame(44, $login['sessionId']);
    $assertSame(2, $login['params']['properties']['connection_type']);
    $assertSame(100, $login['params']['errorType']);

    $navigation = $builder->navigation(100, 44, 1, 2, 300, 400, [
        'source_type' => 9,
        'screen_to' => 99,
    ])->toArray();
    $assertSame('NAV', $navigation['type']);
    $assertSame('GO', $navigation['event']);
    $assertSame(300, $navigation['params']['prev_time']);
    $assertSame(99, $navigation['params']['screen_to']);
    $assertSame(400, $navigation['params']['action_id']);
    $assertSame(1, $navigation['params']['screen_from']);
    $assertSame(9, $navigation['params']['source_type']);

    $openChat = $builder->openChat(100, 44)->toArray();
    $assertSame('open_chat_to_render', $openChat['event']);
    $assertSame('open_chat_to_render', $openChat['params']['spans'][0]['name']);
    $assertSame('messages_list_created', $openChat['params']['spans'][1]['name']);
    $assertSame('messages_render', $openChat['params']['spans'][2]['name']);

    $openChats = $builder->openChats(100, 44)->toArray();
    $assertSame('open_chats_to_render', $openChats['event']);
    $assertSame('chats_tab_created', $openChats['params']['spans'][1]['name']);
    $assertSame('chat_list_render', $openChats['params']['spans'][2]['name']);

    $payload = $builder->toPayload([$builder->login(101, 45)]);
    $assertSame(101, $payload['events'][0]['userId']);
    $assertSame(45, $payload['events'][0]['sessionId']);

    $serviceTransport = new TelemetryTestTransport($frameChunks(Opcode::LOG, 0, ['ok' => true]));
    $serviceManager = new ConnectionManager($serviceTransport, $protocol);
    $serviceManager->open();
    $app = new App($serviceManager, new ClientOptions(['clientSessionId' => 44, 'requestTimeout' => 1.0]));
    $assert($app->api()->telemetry->sendEvents([$builder->login(100, 44)]), 'Telemetry event must be sent');
    $sentLog = $decodeSent($serviceTransport, 0);
    $assertSame(Opcode::LOG, $sentLog->opcode);
    $assertSame('login', $sentLog->payload['events'][0]['event']);
    $assertSame(100, $sentLog->payload['events'][0]['userId']);
    $assert($app->api()->telemetry->sendEvents([]), 'Empty telemetry batch must be a successful no-op');
    $assertSame(1, count($serviceTransport->sent));

    $failingTransport = new TelemetryTestTransport([]);
    $failingManager = new ConnectionManager($failingTransport, $protocol);
    $failingManager->open();
    $failingApp = new App($failingManager, new ClientOptions(['requestTimeout' => 1.0]));
    $assert(!$failingApp->api()->telemetry->login(100, 44), 'Telemetry failures must not escape service boundary');

    $autoTransport = new TelemetryTestTransport(array_merge(
        $frameChunks(Opcode::SESSION_INIT, 0),
        $frameChunks(Opcode::LOGIN, 1, [
            'profile' => ['contact' => ['id' => 700, 'names' => []]],
            'chats' => [],
            'messages' => [],
            'contacts' => [],
            'token' => 'server-token',
            'time' => 999,
            'config' => ['hash' => 'cfg'],
        ]),
        $frameChunks(Opcode::LOG, 2, ['ok' => true])
    ));
    $autoClient = new Client(new ClientOptions([
        'token' => 'local-token',
        'phone' => '+79990000000',
        'deviceId' => 'device-token',
        'mtInstanceId' => 'mt-token',
        'clientSessionId' => 55,
        'telemetry' => true,
        'store' => new TelemetryTestStore(),
        'requestTimeout' => 1.0,
    ]), new ConnectionManager($autoTransport, $protocol));
    $autoClient->open();
    $assertSame(3, count($autoTransport->sent));
    $assertSame(Opcode::SESSION_INIT, $decodeSent($autoTransport, 0)->opcode);
    $assertSame(Opcode::LOGIN, $decodeSent($autoTransport, 1)->opcode);
    $autoLog = $decodeSent($autoTransport, 2);
    $assertSame(Opcode::LOG, $autoLog->opcode);
    $assertSame('login', $autoLog->payload['events'][0]['event']);
    $assertSame(700, $autoLog->payload['events'][0]['userId']);
    $assertSame(55, $autoLog->payload['events'][0]['sessionId']);

    $profile = new RouteProfile(2, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0);
    $rules = new NavigationRules(
        ['test' => $profile],
        [
            Screen::BACKGROUND => [new ScreenTransition(Screen::CHAT, 1)],
            Screen::CHAT => [new ScreenTransition(Screen::CHATS, 1)],
            Screen::CHATS => [new ScreenTransition(Screen::BACKGROUND, 1)],
        ]
    );
    $planner = new NavigationPlanner($rules);
    $assertSame(Screen::BACKGROUND, $planner->currentScreen());
    $assertSame($profile, $planner->newProfile());
    $assertSame(Screen::CHAT, $planner->nextScreen($profile));
    $assertSame([Screen::BACKGROUND], $planner->history());
    $planner->resetToBackground();
    $assertSame([], $planner->history());

    $plannedTransport = new TelemetryTestTransport($frameChunks(Opcode::LOG, 0, ['ok' => true]));
    $plannedManager = new ConnectionManager($plannedTransport, $protocol);
    $plannedManager->open();
    $plannedApp = new App($plannedManager, new ClientOptions(['clientSessionId' => 88, 'requestTimeout' => 1.0]));
    $plannedService = new TelemetryService($plannedApp, new TelemetryPayloadBuilder(), new NavigationPlanner($rules));
    $dialog = Chat::fromArray([
        'id' => 777,
        'type' => ChatType::DIALOG,
        'status' => 'ACTIVE',
        'owner' => 100,
    ]);
    $events = $plannedService->plannedNavigationEvents(100, null, $profile, [$dialog]);
    $assertSame('GO', $events[0]->event);
    $assertSame(Screen::BACKGROUND, $events[0]->params['screen_from']);
    $assertSame(Screen::CHAT, $events[0]->params['screen_to']);
    $assertSame(1, $events[0]->params['action_id']);
    $assertSame(1, $events[0]->params['source_type']);
    $assertSame(777, $events[0]->params['source_id']);
    $assertSame('open_chat_to_render', $events[1]->event);
    $assertSame('GO', $events[2]->event);
    $assertSame(Screen::CHAT, $events[2]->params['screen_from']);
    $assertSame(Screen::CHATS, $events[2]->params['screen_to']);
    $assertSame(2, $events[2]->params['action_id']);
    $assertSame(5, $events[2]->params['source_type']);
    $assertSame(1, $events[2]->params['source_id']);
    $assertSame(2, $events[2]->params['tab_config']);

    $plannedService->resetNavigation();
    $assert($plannedService->sendPlannedNavigation(100, 88, $profile, [$dialog]));
    $plannedLog = $decodeSent($plannedTransport, 0);
    $assertSame(Opcode::LOG, $plannedLog->opcode);
    $assertSame('GO', $plannedLog->payload['events'][0]['event']);
    $assertSame('open_chat_to_render', $plannedLog->payload['events'][1]['event']);
    $assertSame(88, $plannedLog->payload['events'][0]['sessionId']);
};
