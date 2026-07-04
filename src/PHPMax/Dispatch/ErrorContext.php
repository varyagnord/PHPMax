<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

class ErrorContext
{
    /** @var mixed */
    public $client;
    /** @var string */
    public $eventType;
    /** @var mixed */
    public $event;
    /** @var Router */
    public $router;
    /** @var callable|null */
    public $handler;

    /**
     * @param mixed $client
     * @param mixed $event
     */
    public function __construct($client, string $eventType, $event, Router $router, ?callable $handler)
    {
        $this->client = $client;
        $this->eventType = $eventType;
        $this->event = $event;
        $this->router = $router;
        $this->handler = $handler;
    }
}
