<?php

declare(strict_types=1);

use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Exception\ApiException;
use PHPMax\Transport\TransportInterface;

final class FakeRuntimeTransport implements TransportInterface
{
    /** @var list<string> */
    public $chunks;
    /** @var list<string> */
    public $sent = [];
    /** @var bool */
    private $connected = false;

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
        if ($this->chunks === []) {
            throw new RuntimeException('No fake chunks left');
        }
        $chunk = array_shift($this->chunks);
        if (strlen($chunk) !== $length) {
            throw new RuntimeException('Expected recv length ' . $length . ', got chunk length ' . strlen($chunk));
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
    $eventRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::NOTIF_TYPING,
        55,
        ['chatId' => 100],
        Command::REQUEST
    ));
    $responseRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::PING,
        7,
        ['ok' => true],
        Command::RESPONSE
    ));

    $transport = new FakeRuntimeTransport([
        substr($eventRaw, 0, 10),
        substr($eventRaw, 10),
        substr($responseRaw, 0, 10),
        substr($responseRaw, 10),
    ]);
    $manager = new ConnectionManager($transport, $protocol);
    $events = [];
    $manager->setEventHandler(static function (InboundFrame $frame) use (&$events): void {
        $events[] = $frame;
    });
    $manager->open();

    $response = $manager->request(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::PING,
        7,
        ['interactive' => true],
        Command::REQUEST
    ), 1.0);

    $assertSame(Opcode::PING, $response->opcode);
    $assertSame(['ok' => true], $response->payload);
    $assertSame(1, count($events));
    $assertSame(Opcode::NOTIF_TYPING, $events[0]->opcode);
    $assertSame(1, count($transport->sent));

    $managerWrap = new ConnectionManager(new FakeRuntimeTransport([]), $protocol);
    $reflection = new ReflectionClass($managerWrap);
    $seq = $reflection->getProperty('seq');
    $seq->setAccessible(true);
    $seq->setValue($managerWrap, 0xFFFE);
    $assertSame(0xFFFF, $managerWrap->nextSeq());
    $assertSame(0, $managerWrap->nextSeq());

    $assertThrows(\PHPMax\Exception\ProtocolException::class, static function () use ($managerWrap): void {
        $managerWrap->send(new OutboundFrame(TcpProtocol::VERSION, Opcode::PING, 1, []));
    }, 'Closed connection must reject send');

    $appResponseRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::PING,
        0,
        ['ok' => true],
        Command::RESPONSE
    ));
    $transportApp = new FakeRuntimeTransport([
        substr($appResponseRaw, 0, 10),
        substr($appResponseRaw, 10),
    ]);
    $managerApp = new ConnectionManager($transportApp, $protocol);
    $managerApp->open();
    $app = new App($managerApp, 1.0);
    $appResponse = $app->invoke(Opcode::PING, ['interactive' => true]);
    $assertSame(['ok' => true], $appResponse->payload);

    $errorRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::MSG_SEND,
        0,
        [
            'error' => 'message_denied',
            'title' => 'Denied',
            'message' => 'Message rejected',
            'localizedMessage' => 'Сообщение отклонено',
            'details' => ['reason' => 'policy'],
        ],
        Command::ERROR
    ));
    $errorTransport = new FakeRuntimeTransport([
        substr($errorRaw, 0, 10),
        substr($errorRaw, 10),
    ]);
    $errorManager = new ConnectionManager($errorTransport, $protocol);
    $errorManager->open();
    $errorApp = new App($errorManager, 1.0);
    try {
        $errorApp->invoke(Opcode::MSG_SEND, ['chatId' => 1]);
        throw new RuntimeException('ApiException was not thrown');
    } catch (ApiException $e) {
        $assertSame(Opcode::MSG_SEND, $e->opcode());
        $assertSame('message_denied', $e->error());
        $assertSame('Denied', $e->title());
        $assertSame('Message rejected', $e->apiMessage());
        $assertSame('Сообщение отклонено', $e->localizedMessage());
        $assertSame('policy', $e->payload()['details']['reason']);
        $assert(strpos($e->getMessage(), 'Сообщение отклонено') !== false);
        $assert(strpos($e->getMessage(), '[message_denied]') !== false);
    }

    $malformedErrorRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::PING,
        0,
        ['message' => 'Missing required error code'],
        Command::ERROR
    ));
    $malformedTransport = new FakeRuntimeTransport([
        substr($malformedErrorRaw, 0, 10),
        substr($malformedErrorRaw, 10),
    ]);
    $malformedManager = new ConnectionManager($malformedTransport, $protocol);
    $malformedManager->open();
    $malformedApp = new App($malformedManager, 1.0);
    try {
        $malformedApp->invoke(Opcode::PING, []);
        throw new RuntimeException('Fallback ApiException was not thrown');
    } catch (ApiException $e) {
        $assertSame(Opcode::PING, $e->opcode());
        $assertSame('unknown_error', $e->error());
        $assertSame('Unknown error', $e->title());
        $assertSame('Missing required field `error` for PHPMax\\Domain\\MaxApiError', $e->apiMessage());
        $assertSame('Missing required error code', $e->payload()['message']);
    }
};
