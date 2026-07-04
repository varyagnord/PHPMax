<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

use PHPMax\Api\Binding;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Runtime\App;
use Throwable;

class Router
{
    /** @var array<string, list<array{0: callable, 1: list<callable>}>> */
    private $handlers = [];
    /** @var list<callable> */
    private $startHandlers = [];
    /** @var list<Router> */
    private $children = [];
    /** @var list<array{0: callable, 1: string}> */
    private $errorHandlers = [];
    /** @var list<callable> */
    private $disconnectHandlers = [];
    /** @var EventResolver */
    private $resolver;
    /** @var EventMapper */
    private $mapper;

    public function __construct(?EventResolver $resolver = null, ?EventMapper $mapper = null)
    {
        $this->resolver = $resolver ?: new EventResolver();
        $this->mapper = $mapper ?: new EventMapper();
    }

    public function on(string $eventType, callable $handler, callable ...$filters): self
    {
        if (!isset($this->handlers[$eventType])) {
            $this->handlers[$eventType] = [];
        }
        $this->handlers[$eventType][] = [$handler, $filters];

        return $this;
    }

    public function onRaw(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::RAW, $handler, ...$filters);
    }

    public function onMessage(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::MESSAGE_NEW, $handler, ...$filters);
    }

    public function onMessageEdit(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::MESSAGE_EDIT, $handler, ...$filters);
    }

    public function onMessageDelete(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::MESSAGE_DELETE, $handler, ...$filters);
    }

    public function onMessageRead(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::MESSAGE_READ, $handler, ...$filters);
    }

    public function onTyping(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::TYPING, $handler, ...$filters);
    }

    public function onPresence(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::PRESENCE, $handler, ...$filters);
    }

    public function onReactionUpdate(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::REACTION_UPDATE, $handler, ...$filters);
    }

    public function onChatUpdate(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::CHAT_UPDATE, $handler, ...$filters);
    }

    public function onFileReady(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::FILE_READY, $handler, ...$filters);
    }

    public function onVideoReady(callable $handler, callable ...$filters): self
    {
        return $this->on(EventType::VIDEO_READY, $handler, ...$filters);
    }

    public function onError(callable $handler, string $scope = ErrorScope::GLOBAL): self
    {
        if ($scope !== ErrorScope::GLOBAL && $scope !== ErrorScope::LOCAL) {
            throw new \InvalidArgumentException('Invalid error scope: ' . $scope);
        }
        $this->errorHandlers[] = [$handler, $scope];

        return $this;
    }

    public function onDisconnect(callable $handler): self
    {
        $this->disconnectHandlers[] = $handler;

        return $this;
    }

    public function onStart(callable $handler): self
    {
        $this->startHandlers[] = $handler;

        return $this;
    }

    public function includeRouter(Router $router): self
    {
        $this->children[] = $router;

        return $this;
    }

    /**
     * @param mixed $client
     */
    public function emitStart($client): void
    {
        $this->emitStartInTree($client, $this);
    }

    /**
     * @param mixed $client
     */
    private function emitStartInTree($client, Router $root): void
    {
        foreach ($this->startHandlers as $handler) {
            try {
                $handler($client);
            } catch (Throwable $e) {
                $handled = $root->emitError($e, EventType::ON_START, null, $client, $this, $handler);
                if (!$handled) {
                    throw $e;
                }
            }
        }
        foreach ($this->children as $child) {
            $child->emitStartInTree($client, $root);
        }
    }

    /**
     * @param mixed $event
     * @param mixed $client
     */
    public function dispatch(string $eventType, $event, $client): void
    {
        $this->dispatchInTree($eventType, $event, $client, $this);
    }

    /**
     * @param mixed $event
     * @param mixed $client
     */
    private function dispatchInTree(string $eventType, $event, $client, Router $root): void
    {
        foreach ($this->handlers[$eventType] ?? [] as $entry) {
            [$handler, $filters] = $entry;
            try {
                if (!$this->matches($filters, $event)) {
                    continue;
                }
                $handler($event, $client);
            } catch (Throwable $e) {
                $handled = $root->emitError($e, $eventType, $event, $client, $this, $handler);
                if (!$handled) {
                    throw $e;
                }
            }
        }

        foreach ($this->children as $child) {
            $child->dispatchInTree($eventType, $event, $client, $root);
        }
    }

    /**
     * @param mixed $client
     */
    public function dispatchRaw(InboundFrame $frame, $client): void
    {
        $this->dispatch(EventType::RAW, $frame, $client);
    }

    /**
     * @param mixed $client
     */
    public function dispatchFrame(InboundFrame $frame, $client, bool $dispatchRaw = true): void
    {
        $eventType = $this->resolver->resolve($frame);
        if ($eventType !== null) {
            $event = $this->mapper->map($eventType, $frame);
            $this->dispatch($eventType, $this->bindEvent($event, $client), $client);
        }

        if ($dispatchRaw) {
            $this->dispatchRaw($frame, $client);
        }
    }

    /**
     * @param mixed $event
     * @param mixed $client
     */
    public function emitError(Throwable $exception, string $eventType, $event, $client, Router $failedRouter, ?callable $handler): bool
    {
        $handled = false;
        $context = new ErrorContext($client, $eventType, $event, $failedRouter, $handler);
        foreach ($this->errorEntries() as $entry) {
            /** @var Router $owner */
            $owner = $entry[0];
            /** @var callable $errorHandler */
            $errorHandler = $entry[1];
            /** @var string $scope */
            $scope = $entry[2];

            if ($scope === ErrorScope::LOCAL && $owner !== $failedRouter) {
                continue;
            }

            $handled = true;
            try {
                $errorHandler($exception, $context);
            } catch (Throwable $e) {
                return false;
            }
        }

        return $handled;
    }

    public function emitDisconnect(Throwable $exception, bool $reconnect = false, float $delay = 0.0): void
    {
        foreach ($this->disconnectHandlers as $handler) {
            try {
                $handler($exception, $reconnect, $delay);
            } catch (Throwable $e) {
                // Disconnect handlers must not interrupt reconnect/close flow.
            }
        }

        foreach ($this->children as $child) {
            $child->emitDisconnect($exception, $reconnect, $delay);
        }
    }

    /**
     * @param list<callable> $filters
     * @param mixed $event
     */
    private function matches(array $filters, $event): bool
    {
        foreach ($filters as $filter) {
            if (!$filter($event)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $event
     * @param mixed $client
     * @return mixed
     */
    private function bindEvent($event, $client)
    {
        if (!is_object($client) || !method_exists($client, 'app')) {
            return $event;
        }

        $app = $client->app();
        if (!$app instanceof App) {
            return $event;
        }

        return Binding::bindApiModel($app, $event);
    }

    /**
     * @return list<array{0: Router, 1: callable, 2: string}>
     */
    private function errorEntries(): array
    {
        $entries = [];
        foreach ($this->errorHandlers as $entry) {
            $entries[] = [$this, $entry[0], $entry[1]];
        }
        foreach ($this->children as $child) {
            foreach ($child->errorEntries() as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }
}
