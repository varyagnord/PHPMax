<?php

declare(strict_types=1);

use PHPMax\Dispatch\ErrorContext;
use PHPMax\Dispatch\ErrorScope;
use PHPMax\Dispatch\EventType;
use PHPMax\Dispatch\Router;
use PHPMax\Domain\Events\TypingEvent;
use PHPMax\Domain\Message;
use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Exception\ProtocolException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class ErrorScopeDisconnectTransport implements TransportInterface
{
    /** @var bool */
    private $connected = false;

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
    }

    public function recv(int $length, float $timeout): string
    {
        throw new ProtocolException('TCP transport closed by peer');
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $client = new stdClass();
    $assertThrows(InvalidArgumentException::class, static function (): void {
        (new Router())->onError(static function (): void {
        }, 'invalid');
    }, 'Invalid error scope must fail fast instead of becoming global');
    $assertThrows(InvalidArgumentException::class, static function (): void {
        (new Client(new ClientOptions(['requestTimeout' => 1.0])))->onError(static function (): void {
        }, 'invalid');
    }, 'Client::onError must preserve Router scope validation');

    $root = new Router();
    $child = new Router();
    $sibling = new Router();
    $root->includeRouter($child)->includeRouter($sibling);

    $calls = [];
    $root->onError(static function (Throwable $exception, ErrorContext $context) use (&$calls, $child, $client, $assert): void {
        $calls[] = ['root-global', $exception->getMessage(), $context->eventType, $context->event->id];
        $assert($context->router === $child, 'Failed router must be the child router');
        $assert($context->client === $client, 'ErrorContext must keep client object');
        $assert(is_callable($context->handler), 'ErrorContext must expose failed handler');
    });
    $child->onError(static function (Throwable $exception, ErrorContext $context) use (&$calls): void {
        $calls[] = ['child-local', $context->eventType, $context->event->chatId];
    }, ErrorScope::LOCAL);
    $sibling->onError(static function () use (&$calls): void {
        $calls[] = ['sibling-local-should-not-run'];
    }, ErrorScope::LOCAL);
    $child->onMessage(static function (Message $message): void {
        throw new RuntimeException('message boom ' . $message->id);
    });
    $root->onRaw(static function (InboundFrame $frame) use (&$calls): void {
        $calls[] = ['raw-after-handled-error', $frame->opcode];
    });

    $root->dispatchFrame(new InboundFrame(Opcode::NOTIF_MESSAGE, Command::REQUEST, 1, [
        'chatId' => 777,
        'message' => ['id' => 42, 'time' => 100, 'type' => 'USER', 'text' => 'hi'],
    ]), $client);

    $assertSame([
        ['root-global', 'message boom 42', EventType::MESSAGE_NEW, 42],
        ['child-local', EventType::MESSAGE_NEW, 777],
        ['raw-after-handled-error', Opcode::NOTIF_MESSAGE],
    ], $calls);

    $unhandled = new Router();
    $unhandled->onTyping(static function (TypingEvent $event): void {
        throw new RuntimeException('unhandled typing');
    });
    $assertThrows(RuntimeException::class, static function () use ($unhandled, $client): void {
        $unhandled->dispatchFrame(new InboundFrame(Opcode::NOTIF_TYPING, Command::REQUEST, 2, [
            'chatId' => 1,
            'userId' => 2,
        ]), $client);
    });

    $filterCalls = [];
    $filterRouter = new Router();
    $filterRouter->onError(static function (Throwable $exception, ErrorContext $context) use (&$filterCalls): void {
        $filterCalls[] = [$exception->getMessage(), $context->eventType, is_callable($context->handler)];
    }, ErrorScope::LOCAL);
    $filterRouter->onTyping(
        static function (): void {
            throw new RuntimeException('handler should not run');
        },
        static function (): bool {
            throw new LogicException('filter boom');
        }
    );
    $filterRouter->dispatchFrame(new InboundFrame(Opcode::NOTIF_TYPING, Command::REQUEST, 3, [
        'chatId' => 3,
        'userId' => 4,
    ]), $client);
    $assertSame([['filter boom', EventType::TYPING, true]], $filterCalls);

    $brokenErrorHandler = new Router();
    $brokenErrorHandler->onError(static function (): void {
        throw new RuntimeException('error handler boom');
    });
    $brokenErrorHandler->onTyping(static function (): void {
        throw new InvalidArgumentException('original error');
    });
    $assertThrows(InvalidArgumentException::class, static function () use ($brokenErrorHandler, $client): void {
        $brokenErrorHandler->dispatchFrame(new InboundFrame(Opcode::NOTIF_TYPING, Command::REQUEST, 4, [
            'chatId' => 5,
            'userId' => 6,
        ]), $client);
    });

    $startCalls = [];
    $startRoot = new Router();
    $startChild = new Router();
    $startRoot->includeRouter($startChild);
    $startRoot->onError(static function (Throwable $exception, ErrorContext $context) use (&$startCalls): void {
        $startCalls[] = ['start-root', $context->eventType, $context->event];
    });
    $startChild->onError(static function (Throwable $exception, ErrorContext $context) use (&$startCalls, $startChild): void {
        $startCalls[] = ['start-child-local', $context->router === $startChild];
    }, ErrorScope::LOCAL);
    $startChild->onStart(static function (): void {
        throw new RuntimeException('start boom');
    });
    $startRoot->emitStart($client);
    $assertSame([
        ['start-root', EventType::ON_START, null],
        ['start-child-local', true],
    ], $startCalls);

    $disconnectCalls = [];
    $disconnectRoot = new Router();
    $disconnectChild = new Router();
    $disconnectSibling = new Router();
    $disconnectRoot->includeRouter($disconnectChild)->includeRouter($disconnectSibling);
    $disconnectRoot->onDisconnect(static function (Throwable $exception, bool $reconnect, float $delay) use (&$disconnectCalls): void {
        $disconnectCalls[] = ['root', $exception->getMessage(), $reconnect, $delay];
    });
    $disconnectChild->onDisconnect(static function () use (&$disconnectCalls): void {
        $disconnectCalls[] = ['child-before-error'];
        throw new RuntimeException('disconnect handler boom');
    });
    $disconnectSibling->onDisconnect(static function (Throwable $exception, bool $reconnect, float $delay) use (&$disconnectCalls): void {
        $disconnectCalls[] = ['sibling', $reconnect, $delay];
    });
    $disconnectRoot->emitDisconnect(new RuntimeException('network down'), true, 1.5);
    $assertSame([
        ['root', 'network down', true, 1.5],
        ['child-before-error'],
        ['sibling', true, 1.5],
    ], $disconnectCalls);

    $runtimeDisconnectCalls = [];
    $runtimeTransport = new ErrorScopeDisconnectTransport();
    $runtimeClient = new Client(
        new ClientOptions(['requestTimeout' => 1.0, 'executionSafetyMargin' => 0.0, 'reconnect' => false]),
        new ConnectionManager($runtimeTransport, new TcpProtocol())
    );
    $runtimeClient->onDisconnect(static function (Throwable $exception, bool $reconnect, float $delay) use (&$runtimeDisconnectCalls): void {
        $runtimeDisconnectCalls[] = [$exception->getMessage(), $reconnect, $delay];
    });
    $runtimeClient->open();
    $assertThrows(ProtocolException::class, static function () use ($runtimeClient): void {
        $runtimeClient->runFor(1);
    });
    $assertSame([['TCP transport closed by peer', false, 0.0]], $runtimeDisconnectCalls);
    $assert(!$runtimeClient->connection()->isOpen(), 'runFor must close connection after non-timeout protocol failure');
};
