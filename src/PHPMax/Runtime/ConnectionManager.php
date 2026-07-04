<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

use PHPMax\Exception\ProtocolException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\FrameProtocolInterface;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Transport\TransportInterface;

class ConnectionManager
{
    /** @var TransportInterface */
    private $transport;
    /** @var FrameProtocolInterface */
    private $protocol;
    /** @var FrameReaderInterface */
    private $reader;
    /** @var callable|null */
    private $onEvent;
    /** @var list<callable> */
    private $eventListeners;
    /** @var int */
    private $seq = -1;
    /** @var bool */
    private $open = false;

    public function __construct(TransportInterface $transport, ?FrameProtocolInterface $protocol = null, ?FrameReaderInterface $reader = null)
    {
        $this->transport = $transport;
        $this->protocol = $protocol ?: new TcpProtocol();
        $this->reader = $reader ?: new TcpFrameReader();
        $this->onEvent = null;
        $this->eventListeners = [];
    }

    public function setEventHandler(?callable $handler): void
    {
        $this->onEvent = $handler;
    }

    public function addEventListener(callable $handler): void
    {
        $this->eventListeners[] = $handler;
    }

    public function open(): void
    {
        if ($this->open) {
            return;
        }
        $this->transport->connect();
        $this->open = true;
    }

    public function close(): void
    {
        $this->transport->close();
        $this->open = false;
    }

    public function send(OutboundFrame $frame): void
    {
        if (!$this->isOpen()) {
            throw new ProtocolException('Connection is not open');
        }
        $this->transport->send($this->protocol->encode($frame));
    }

    public function request(OutboundFrame $frame, float $timeout): InboundFrame
    {
        if (!$this->isOpen()) {
            throw new ProtocolException('Connection is not open');
        }

        $this->transport->send($this->protocol->encode($frame));
        $deadline = microtime(true) + $timeout;

        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new ProtocolException('Request timed out for seq=' . $frame->seq);
            }

            $inbound = $this->readFrame($remaining);
            if (
                ($inbound->cmd === Command::RESPONSE || $inbound->cmd === Command::ERROR)
                && $inbound->seq === $frame->seq
            ) {
                return $inbound;
            }

            $this->dispatchEvent($inbound);
        }
    }

    public function readFrame(float $timeout): InboundFrame
    {
        if (!$this->isOpen()) {
            throw new ProtocolException('Connection is not open');
        }

        return $this->protocol->decode($this->reader->read($this->transport, $timeout));
    }

    public function protocolVersion(): int
    {
        return $this->protocol->version();
    }

    public function nextSeq(): int
    {
        $this->seq = ($this->seq + 1) % 0x10000;
        return $this->seq;
    }

    public function isOpen(): bool
    {
        return $this->open && $this->transport->connected();
    }

    public function dispatchEvent(InboundFrame $frame): void
    {
        foreach ($this->eventListeners as $listener) {
            call_user_func($listener, $frame);
        }

        if ($this->onEvent === null) {
            return;
        }

        call_user_func($this->onEvent, $frame);
    }
}
