<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Ws;

use PHPMax\Protocol\FrameProtocolInterface;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\OutboundFrame;

final class WsProtocol implements FrameProtocolInterface
{
    public const VERSION = 11;

    public function version(): int
    {
        return self::VERSION;
    }

    public function encode(OutboundFrame $frame): string
    {
        $encoded = json_encode([
            'ver' => $frame->ver,
            'opcode' => $frame->opcode,
            'cmd' => $frame->cmd,
            'seq' => $frame->seq,
            'payload' => $frame->payload,
        ], JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    public function decode(string $raw): InboundFrame
    {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new InboundFrame(0, 0, null, null, null);
        }

        $payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : null;

        return new InboundFrame(
            isset($data['opcode']) ? (int) $data['opcode'] : 0,
            isset($data['cmd']) ? (int) $data['cmd'] : 0,
            isset($data['seq']) ? (int) $data['seq'] : null,
            $payload,
            $data
        );
    }
}
