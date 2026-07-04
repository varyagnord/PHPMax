<?php

declare(strict_types=1);

use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Dispatch\EventResolver;
use PHPMax\Dispatch\EventType;
use PHPMax\Dispatch\Router;
use PHPMax\Domain\Chat;
use PHPMax\Domain\Events\FileUploadSignal;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\Events\MessageReadEvent;
use PHPMax\Domain\Events\PresenceEvent;
use PHPMax\Domain\Events\ReactionUpdateEvent;
use PHPMax\Domain\Events\TypingEvent;
use PHPMax\Domain\Events\VideoUploadSignal;
use PHPMax\Domain\Message;
use PHPMax\Exception\ValidationException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class EventDispatchTestTransport implements TransportInterface
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
            throw new RuntimeException('No fake dispatch chunks left');
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
    $resolver = new EventResolver();
    $assertSame(null, $resolver->resolve(new InboundFrame(Opcode::NOTIF_TYPING, Command::RESPONSE, null, [
        'chatId' => 1,
        'userId' => 2,
    ])), 'Non-request frames must not resolve to typed events');
    $assertSame(null, $resolver->resolve(new InboundFrame(999999, Command::REQUEST, null, [])));

    $client = new stdClass();
    $router = new Router();
    $seen = [];
    $router
        ->onMessage(static function (Message $message) use (&$seen): void {
            $seen[] = ['message', $message->id, $message->chatId, $message->text];
        }, static function (Message $message): bool {
            return $message->text === 'hello';
        })
        ->onMessageEdit(static function (Message $message) use (&$seen): void {
            $seen[] = ['edit', $message->id];
        })
        ->onMessageDelete(static function (MessageDeleteEvent $event) use (&$seen): void {
            $seen[] = ['delete', $event->chatId, $event->messageIds, $event->ttl];
        })
        ->onChatUpdate(static function (Chat $chat) use (&$seen): void {
            $seen[] = ['chat', $chat->id, $chat->title];
        })
        ->onTyping(static function (TypingEvent $event) use (&$seen): void {
            $seen[] = ['typing', $event->chatId, $event->userId];
        })
        ->onMessageRead(static function (MessageReadEvent $event) use (&$seen): void {
            $seen[] = ['read', $event->chatId, $event->userId, $event->mark, $event->setAsUnread];
        })
        ->onPresence(static function (PresenceEvent $event) use (&$seen): void {
            $seen[] = ['presence', $event->userId, $event->presence->status, $event->presence->seen];
        })
        ->onReactionUpdate(static function (ReactionUpdateEvent $event) use (&$seen): void {
            $seen[] = ['reaction', $event->messageId, $event->chatId, $event->totalCount, $event->counters[0]->reaction];
        })
        ->onFileReady(static function (FileUploadSignal $event) use (&$seen): void {
            $seen[] = ['file', $event->fileId];
        })
        ->onVideoReady(static function (VideoUploadSignal $event) use (&$seen): void {
            $seen[] = ['video', $event->videoId];
        })
        ->onRaw(static function (InboundFrame $frame) use (&$seen): void {
            $seen[] = ['raw', $frame->opcode];
        });

    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_MESSAGE, Command::REQUEST, 10, [
        'chatId' => 100,
        'message' => ['id' => 1, 'time' => 111, 'type' => 'USER', 'text' => 'hello'],
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::MSG_EDIT, Command::REQUEST, 11, [
        'chatId' => 100,
        'message' => ['id' => 1, 'time' => 112, 'type' => 'USER', 'text' => 'edited', 'status' => 'EDITED'],
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_MESSAGE, Command::REQUEST, 12, [
        'chatId' => 100,
        'message' => ['id' => 2, 'time' => 113, 'type' => 'USER', 'text' => 'removed', 'status' => 'REMOVED'],
        'ttl' => true,
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_MSG_DELETE, Command::REQUEST, 13, [
        'chat' => ['id' => 101, 'type' => 'CHAT', 'status' => 'ACTIVE', 'owner' => 7],
        'messageIds' => [3, '4'],
        'ttl' => false,
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_CHAT, Command::REQUEST, 14, [
        'chat' => ['id' => 101, 'type' => 'CHAT', 'status' => 'ACTIVE', 'owner' => 7, 'title' => 'group'],
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_TYPING, Command::REQUEST, 15, [
        'chatId' => 101,
        'userId' => 9,
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_MARK, Command::REQUEST, 16, [
        'setAsUnread' => false,
        'chatId' => 101,
        'userId' => 9,
        'mark' => 555,
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_PRESENCE, Command::REQUEST, 17, [
        'presence' => ['status' => 1, 'seen' => 444],
        'userId' => 9,
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_MSG_REACTIONS_CHANGED, Command::REQUEST, 18, [
        'messageId' => '1',
        'chatId' => 101,
        'totalCount' => 2,
        'counters' => [['count' => 2, 'reaction' => '👍']],
    ]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_ATTACH, Command::REQUEST, 19, ['fileId' => 77]), $client);
    $router->dispatchFrame(new InboundFrame(Opcode::NOTIF_ATTACH, Command::REQUEST, 20, ['videoId' => 88]), $client);

    $assertSame([
        ['message', 1, 100, 'hello'],
        ['raw', Opcode::NOTIF_MESSAGE],
        ['edit', 1],
        ['raw', Opcode::MSG_EDIT],
        ['delete', 100, [2], true],
        ['raw', Opcode::NOTIF_MESSAGE],
        ['delete', 101, [3, 4], false],
        ['raw', Opcode::NOTIF_MSG_DELETE],
        ['chat', 101, 'group'],
        ['raw', Opcode::NOTIF_CHAT],
        ['typing', 101, 9],
        ['raw', Opcode::NOTIF_TYPING],
        ['read', 101, 9, 555, false],
        ['raw', Opcode::NOTIF_MARK],
        ['presence', 9, 1, 444],
        ['raw', Opcode::NOTIF_PRESENCE],
        ['reaction', '1', 101, 2, '👍'],
        ['raw', Opcode::NOTIF_MSG_REACTIONS_CHANGED],
        ['file', 77],
        ['raw', Opcode::NOTIF_ATTACH],
        ['video', 88],
        ['raw', Opcode::NOTIF_ATTACH],
    ], $seen);

    $falseyPayloadSeen = [];
    $falseyPayloadRouter = new Router();
    $falseyPayloadRouter->onChatUpdate(static function ($event) use (&$falseyPayloadSeen): void {
        $falseyPayloadSeen[] = [
            $event instanceof InboundFrame,
            $event instanceof InboundFrame ? $event->opcode : null,
        ];
    });
    $falseyPayloadRouter->dispatchFrame(new InboundFrame(Opcode::NOTIF_CHAT, Command::REQUEST, 140, []), $client, false);
    $assertSame([[true, Opcode::NOTIF_CHAT]], $falseyPayloadSeen, 'Empty known event payload must keep PyMax raw-frame fallback');

    $strictChatSeen = [];
    $strictChatRouter = new Router();
    $strictChatRouter
        ->onChatUpdate(static function (Chat $chat): void {
        })
        ->onRaw(static function () use (&$strictChatSeen): void {
            $strictChatSeen[] = 'raw';
        });
    $assertThrows(ValidationException::class, static function () use ($strictChatRouter, $client): void {
        $strictChatRouter->dispatchFrame(new InboundFrame(Opcode::NOTIF_CHAT, Command::REQUEST, 141, [
            'id' => 102,
            'type' => 'CHAT',
            'status' => 'ACTIVE',
            'owner' => 7,
        ]), $client);
    }, 'CHAT_UPDATE must require nested `chat` payload like PyMax');
    $assertThrows(ValidationException::class, static function () use ($strictChatRouter, $client): void {
        $strictChatRouter->dispatchFrame(new InboundFrame(Opcode::NOTIF_CHAT, Command::REQUEST, 142, [
            'chat' => 'invalid',
        ]), $client);
    }, 'CHAT_UPDATE scalar `chat` payload must fail before handler dispatch');
    $assertThrows(ValidationException::class, static function () use ($strictChatRouter, $client): void {
        $strictChatRouter->dispatchFrame(new InboundFrame(Opcode::NOTIF_CHAT, Command::REQUEST, 143, [
            'chat' => [],
        ]), $client);
    }, 'CHAT_UPDATE empty nested `chat` payload must fail model validation');
    $assertSame([], $strictChatSeen, 'Malformed typed event payload must not continue to raw fallback');

    $protocol = new TcpProtocol();
    $internalTransport = new EventDispatchTestTransport([]);
    $internalManager = new ConnectionManager($internalTransport, $protocol);
    $internalApp = new App($internalManager, 1.0);
    $internalRouter = new Router();
    $internalClient = new class($internalApp) {
        private $app;

        public function __construct(App $app)
        {
            $this->app = $app;
        }

        public function app(): App
        {
            return $this->app;
        }
    };
    $internalSeen = [];
    $internalApp->onInternal(EventType::MESSAGE_NEW, static function (Message $message) use (&$internalSeen): void {
        $internalSeen[] = ['internal', $message->id];
    });
    $internalRouter
        ->onMessage(static function (Message $message) use (&$internalSeen): void {
            $internalSeen[] = ['user', $message->id];
        })
        ->onRaw(static function (InboundFrame $frame) use (&$internalSeen): void {
            $internalSeen[] = ['raw', $frame->opcode];
        });
    $internalManager->setEventHandler(static function (InboundFrame $frame) use ($internalRouter, $internalClient): void {
        $internalRouter->dispatchFrame($frame, $internalClient);
    });
    $internalManager->dispatchEvent(new InboundFrame(Opcode::NOTIF_MESSAGE, Command::REQUEST, 21, [
        'chatId' => 303,
        'message' => ['id' => 73, 'time' => 1001, 'type' => 'USER', 'text' => 'internal-first'],
    ]));
    $assertSame([
        ['internal', 73],
        ['user', 73],
        ['raw', Opcode::NOTIF_MESSAGE],
    ], $internalSeen, 'Internal event handlers must run before user handlers and must not receive raw fallback');

    $chunkFrame = static function (int $opcode, int $seq, array $payload, int $cmd = Command::REQUEST) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, $cmd));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $chunks = array_merge(
        $chunkFrame(Opcode::NOTIF_MESSAGE, 999, [
            'chatId' => 202,
            'message' => ['id' => 42, 'time' => 1000, 'type' => 'USER', 'text' => 'event-before-response'],
        ]),
        $chunkFrame(Opcode::PING, 0, ['ok' => true], Command::RESPONSE)
    );
    $transport = new EventDispatchTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $runtimeSeen = [];
    $runtimeRouter = new Router();
    $runtimeRouter
        ->onMessage(static function (Message $message) use (&$runtimeSeen): void {
            $runtimeSeen[] = ['message', $message->id, $message->chatId];
        })
        ->onRaw(static function (InboundFrame $frame) use (&$runtimeSeen): void {
            $runtimeSeen[] = ['raw', $frame->opcode];
        });
    $runtimeClient = new Client(new ClientOptions(['requestTimeout' => 1.0]), $manager, $runtimeRouter);
    $runtimeClient->open();
    $response = $runtimeClient->invoke(Opcode::PING, ['interactive' => true]);

    $assertSame(['ok' => true], $response->payload);
    $assertSame([
        ['message', 42, 202],
        ['raw', Opcode::NOTIF_MESSAGE],
    ], $runtimeSeen);
};
