<?php

declare(strict_types=1);

use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class BotsServiceTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake bot chunks left');
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

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $frameChunks = static function (array $payload, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(
            TcpProtocol::VERSION,
            Opcode::WEB_APP_INIT_DATA,
            $seq,
            $payload,
            Command::RESPONSE
        ));

        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (BotsServiceTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $transport = new BotsServiceTestTransport(array_merge(
        $frameChunks(['queryId' => 'query-1', 'url' => 'https://bot.test/app?query=query-1'], 0),
        $frameChunks(['queryId' => 'query-2', 'url' => 'https://bot.test/app?query=query-2'], 1)
    ));
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, new ClientOptions(['requestTimeout' => 1.0]));

    $initData = $app->api()->bots->getInitData(100, 200, 'start');
    $assertSame('query-1', $initData->queryId);
    $assertSame('https://bot.test/app?query=query-1', $initData->url);
    $sent = $decodeSent($transport, 0);
    $assertSame(Opcode::WEB_APP_INIT_DATA, $sent->opcode);
    $assertSame(['botId' => 100, 'chatId' => 200, 'startParam' => 'start'], $sent->payload);

    $client = new Client(new ClientOptions(['requestTimeout' => 1.0]), $manager);
    $clientInitData = $client->getBotInitData(101);
    $assertSame('query-2', $clientInitData->queryId);
    $clientSent = $decodeSent($transport, 1);
    $assertSame(Opcode::WEB_APP_INIT_DATA, $clientSent->opcode);
    $assertSame(['botId' => 101], $clientSent->payload);

    $emptyTransport = new BotsServiceTestTransport($frameChunks([], 0));
    $emptyManager = new ConnectionManager($emptyTransport, $protocol);
    $emptyManager->open();
    $emptyApp = new App($emptyManager, new ClientOptions(['requestTimeout' => 1.0]));
    $assertThrows(\PHPMax\Exception\PHPMaxException::class, static function () use ($emptyApp): void {
        $emptyApp->api()->bots->getInitData(102);
    }, 'getInitData must require a payload like PyMax require_payload_model');
};
