<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

use PHPMax\Exception\ProtocolException;
use PHPMax\Protocol\Tcp\TcpPacketFramer;
use PHPMax\Transport\TransportInterface;

class TcpFrameReader implements FrameReaderInterface
{
    /** @var TcpPacketFramer */
    private $framer;

    public function __construct(?TcpPacketFramer $framer = null)
    {
        $this->framer = $framer ?: new TcpPacketFramer();
    }

    public function read(TransportInterface $transport, float $timeout): string
    {
        $header = $transport->recv(TcpPacketFramer::HEADER_SIZE, $timeout);
        $payloadLen = $this->framer->unpackHeader($header);
        if ($payloadLen === null) {
            throw new ProtocolException('Failed to unpack TCP packet header');
        }

        return $header . $transport->recv($payloadLen, $timeout);
    }
}
