<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

class PackedPacket
{
    /** @var TcpPacketHeader */
    public $header;
    /** @var string */
    public $payloadBytes;

    public function __construct(TcpPacketHeader $header, string $payloadBytes)
    {
        $this->header = $header;
        $this->payloadBytes = $payloadBytes;
    }
}

