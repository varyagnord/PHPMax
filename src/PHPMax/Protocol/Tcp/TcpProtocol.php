<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use PHPMax\Protocol\FrameProtocolInterface;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\OutboundFrame;

final class TcpProtocol implements FrameProtocolInterface
{
    public const VERSION = 10;

    /** @var TcpPacketFramer */
    private $framer;
    /** @var MsgpackPayloadCodec */
    private $serializer;
    /** @var TcpPayloadDecoder */
    private $payloadDecoder;

    public function __construct(
        ?TcpPacketFramer $framer = null,
        ?MsgpackPayloadCodec $serializer = null,
        ?TcpPayloadDecoder $payloadDecoder = null
    ) {
        $this->framer = $framer ?: new TcpPacketFramer();
        $this->serializer = $serializer ?: new MsgpackPayloadCodec();
        $this->payloadDecoder = $payloadDecoder ?: new TcpPayloadDecoder($this->serializer);
    }

    public function version(): int
    {
        return self::VERSION;
    }

    public function encode(OutboundFrame $frame): string
    {
        $payloadBytes = $frame->payload !== null ? $this->serializer->encode($frame->payload) : '';

        return $this->framer->pack(
            self::VERSION,
            $frame->cmd,
            $frame->seq,
            $frame->opcode,
            0,
            $payloadBytes
        );
    }

    public function decode(string $raw): InboundFrame
    {
        $packet = $this->framer->unpack($raw);
        if ($packet === null) {
            return new InboundFrame(0, 0, null, null, null);
        }

        $payload = $this->payloadDecoder->decode($packet->payloadBytes, $packet->header->flags);

        return new InboundFrame(
            $packet->header->opcode,
            $packet->header->cmd,
            $packet->header->seq,
            $payload,
            $payload
        );
    }
}
