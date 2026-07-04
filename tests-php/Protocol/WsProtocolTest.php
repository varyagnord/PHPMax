<?php

declare(strict_types=1);

use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Auth\AuthService;
use PHPMax\Auth\AuthFlowInterface;
use PHPMax\Auth\AuthResult;
use PHPMax\Auth\QrAuthFlow;
use PHPMax\Auth\QrHandlerInterface;
use PHPMax\Config\ClientOptions;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Ws\WsProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Runtime\WebSocketFrameReader;
use PHPMax\Transport\MessageTransportInterface;
use PHPMax\Transport\TransportInterface;
use PHPMax\WebClient;

final class WsProtocolTestTransport implements TransportInterface, MessageTransportInterface
{
    /** @var list<string> */
    private $messages;
    /** @var bool */
    private $connected = false;
    /** @var list<string> */
    public $sent = [];

    /**
     * @param list<string> $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
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
        return $this->recvMessage($timeout);
    }

    public function recvMessage(float $timeout): string
    {
        $message = array_shift($this->messages);
        if ($message === null) {
            throw new RuntimeException('No fake WebSocket messages left');
        }

        return $message;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class WsProtocolQrHandler implements QrHandlerInterface
{
    /** @var list<string> */
    public $urls = [];

    public function showQr(string $qrUrl): void
    {
        $this->urls[] = $qrUrl;
    }
}

final class WsProtocolCustomAuthFlow implements AuthFlowInterface
{
    public function authenticate(AuthService $authService, ClientOptions $options): AuthResult
    {
        return new AuthResult('custom-web-token');
    }
}

final class WsProtocolByteOnlyTransport implements TransportInterface
{
    public function connect(): void
    {
    }

    public function close(): void
    {
    }

    public function send(string $data): void
    {
    }

    public function recv(int $length, float $timeout): string
    {
        return '';
    }

    public function connected(): bool
    {
        return true;
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new WsProtocol();
    $encoded = $protocol->encode(new OutboundFrame(
        WsProtocol::VERSION,
        Opcode::PING,
        7,
        ['interactive' => true],
        Command::REQUEST
    ));
    $decodedJson = json_decode($encoded, true);
    $assertSame(11, $decodedJson['ver']);
    $assertSame(Opcode::PING, $decodedJson['opcode']);
    $assertSame(Command::REQUEST, $decodedJson['cmd']);
    $assertSame(7, $decodedJson['seq']);
    $assertSame(['interactive' => true], $decodedJson['payload']);

    $decoded = $protocol->decode('{"opcode":1,"cmd":1,"seq":7,"payload":{"ok":true}}');
    $assert($decoded instanceof InboundFrame);
    $assertSame(Opcode::PING, $decoded->opcode);
    $assertSame(Command::RESPONSE, $decoded->cmd);
    $assertSame(7, $decoded->seq);
    $assertSame(['ok' => true], $decoded->payload);
    $assertSame(null, $protocol->decode('not-json')->seq);
    $assertSame(0, $protocol->decode('not-json')->opcode);

    $transport = new WsProtocolTestTransport([
        '{"opcode":129,"cmd":0,"seq":22,"payload":{"chatId":10,"userId":20}}',
        '{"opcode":1,"cmd":1,"seq":0,"payload":{"ok":true}}',
    ]);
    $manager = new ConnectionManager($transport, $protocol, new WebSocketFrameReader());
    $events = [];
    $manager->setEventHandler(static function (InboundFrame $frame) use (&$events): void {
        $events[] = $frame;
    });
    $manager->open();
    $response = $manager->request(new OutboundFrame(
        $manager->protocolVersion(),
        Opcode::PING,
        $manager->nextSeq(),
        ['interactive' => true],
        Command::REQUEST
    ), 1.0);
    $assertSame(['ok' => true], $response->payload);
    $assertSame(1, count($events));
    $assertSame(Opcode::NOTIF_TYPING, $events[0]->opcode);
    $sentFrame = json_decode($transport->sent[0], true);
    $assertSame(11, $sentFrame['ver']);
    $assertSame(Opcode::PING, $sentFrame['opcode']);

    $appTransport = new WsProtocolTestTransport([
        '{"opcode":1,"cmd":1,"seq":0,"payload":{"ok":true}}',
    ]);
    $appManager = new ConnectionManager($appTransport, new WsProtocol(), new WebSocketFrameReader());
    $appManager->open();
    $app = new App($appManager, new ClientOptions(['requestTimeout' => 1.0]));
    $appResponse = $app->invoke(Opcode::PING, ['interactive' => true]);
    $assertSame(['ok' => true], $appResponse->payload);
    $appSent = json_decode($appTransport->sent[0], true);
    $assertSame(11, $appSent['ver'], 'App::invoke must use active protocol version');

    $webClientTransport = new WsProtocolTestTransport([]);
    $webClientManager = new ConnectionManager($webClientTransport, new WsProtocol(), new WebSocketFrameReader());
    $webClient = new WebClient(new ClientOptions(['requestTimeout' => 1.0]), new WsProtocolQrHandler(), $webClientManager);
    $assertSame(DeviceType::WEB, $webClient->app()->options()->userAgent->deviceType);
    $assert($webClient->app()->options()->authFlow instanceof QrAuthFlow, 'WebClient must default to QR auth flow');

    $customFlow = new WsProtocolCustomAuthFlow();
    $customWebClient = new WebClient(
        new ClientOptions(['authFlow' => $customFlow]),
        new WsProtocolQrHandler(),
        new ConnectionManager(new WsProtocolTestTransport([]), new WsProtocol(), new WebSocketFrameReader())
    );
    $assert($customWebClient->app()->options()->authFlow === $customFlow, 'WebClient must preserve an explicitly configured auth flow');
    $assertSame(DeviceType::WEB, $customWebClient->app()->options()->userAgent->deviceType, 'WebClient must still force web user-agent for custom auth flow');

    $webOptions = new ClientOptions();
    $webOptions->userAgent = \PHPMax\Api\Session\MobileUserAgentPayload::defaultWeb();
    $existingWebAgent = $webOptions->userAgent;
    $existingWebClient = new WebClient(
        $webOptions,
        new WsProtocolQrHandler(),
        new ConnectionManager(new WsProtocolTestTransport([]), new WsProtocol(), new WebSocketFrameReader())
    );
    $assert($existingWebClient->app()->options()->userAgent === $existingWebAgent, 'WebClient must preserve explicitly configured web user-agent object');

    $assertThrows(\PHPMax\Exception\ProtocolException::class, static function (): void {
        (new WebSocketFrameReader())->read(new WsProtocolByteOnlyTransport(), 1.0);
    }, 'Empty WebSocket message transport must surface read errors');
};
