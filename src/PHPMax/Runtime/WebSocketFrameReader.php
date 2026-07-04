<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

use PHPMax\Exception\ProtocolException;
use PHPMax\Transport\MessageTransportInterface;
use PHPMax\Transport\TransportInterface;

class WebSocketFrameReader implements FrameReaderInterface
{
    public function read(TransportInterface $transport, float $timeout): string
    {
        if (!$transport instanceof MessageTransportInterface) {
            throw new ProtocolException('WebSocket frame reader requires message transport');
        }

        return $transport->recvMessage($timeout);
    }
}
