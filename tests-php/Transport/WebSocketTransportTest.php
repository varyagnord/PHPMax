<?php

declare(strict_types=1);

use PHPMax\Exception\ProtocolException;
use PHPMax\Transport\WebSocketTransport;

final class WsTransportHardeningHarness
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public static function validateHandshake(string $headers, string $key): void
    {
        $transport = new WebSocketTransport('ws://example.test/websocket', 1.0);
        $method = new ReflectionMethod(WebSocketTransport::class, 'validateHandshakeResponse');
        $method->setAccessible(true);
        $method->invoke($transport, $headers, $key);
    }

    public static function acceptKey(string $key): string
    {
        return base64_encode(sha1($key . self::GUID, true));
    }

    /**
     * @return array{0: WebSocketTransport, 1: resource}
     */
    public static function transportWithInput(string $input): array
    {
        if (!function_exists('stream_socket_pair')) {
            throw new RuntimeException('stream_socket_pair is required for WebSocketTransport tests');
        }

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair === false) {
            throw new RuntimeException('Failed to create socket pair for WebSocketTransport tests');
        }

        stream_set_blocking($pair[0], true);
        stream_set_blocking($pair[1], true);
        self::writeAll($pair[1], $input);

        $transport = new WebSocketTransport('ws://example.test/websocket', 1.0);
        $property = new ReflectionProperty(WebSocketTransport::class, 'stream');
        $property->setAccessible(true);
        $property->setValue($transport, $pair[0]);

        return [$transport, $pair[1]];
    }

    public static function serverFrame(int $opcode, string $payload, bool $fin = true, int $rsv = 0): string
    {
        $first = ($fin ? 0x80 : 0x00) | ($rsv & 0x70) | ($opcode & 0x0F);
        $length = strlen($payload);
        if ($length <= 125) {
            return chr($first) . chr($length) . $payload;
        }
        if ($length <= 0xFFFF) {
            return chr($first) . chr(126) . pack('n', $length) . $payload;
        }

        return chr($first) . chr(127) . pack('N2', intdiv($length, 4294967296), $length % 4294967296) . $payload;
    }

    public static function maskedServerFrame(int $opcode, string $payload): string
    {
        $mask = "\x01\x02\x03\x04";
        $masked = '';
        $length = strlen($payload);
        for ($i = 0; $i < $length; $i++) {
            $masked .= $payload[$i] ^ $mask[$i % 4];
        }

        return chr(0x80 | ($opcode & 0x0F)) . chr(0x80 | $length) . $mask . $masked;
    }

    public static function extendedLengthFrame(int $opcode, int $length, string $payload = '', bool $fin = true): string
    {
        $first = ($fin ? 0x80 : 0x00) | ($opcode & 0x0F);
        if ($length <= 0xFFFF) {
            return chr($first) . chr(126) . pack('n', $length) . $payload;
        }

        return chr($first) . chr(127) . pack('N2', intdiv($length, 4294967296), $length % 4294967296) . $payload;
    }

    private static function writeAll($stream, string $data): void
    {
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('Failed to write test WebSocket bytes');
            }
            $written += $chunk;
        }
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $key = base64_encode(str_repeat('a', 16));
    $accept = WsTransportHardeningHarness::acceptKey($key);
    WsTransportHardeningHarness::validateHandshake(
        "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: WebSocket\r\n" .
        "Connection: keep-alive, Upgrade\r\n" .
        'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n",
        $key
    );
    $assert(true, 'Valid WebSocket handshake response must be accepted');

    $assertThrows(ProtocolException::class, static function () use ($key, $accept): void {
        WsTransportHardeningHarness::validateHandshake(
            "HTTP/1.1 200 101 Not Switching\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n",
            $key
        );
    }, 'Handshake status must be an actual HTTP 101 response');

    $assertThrows(ProtocolException::class, static function () use ($key, $accept): void {
        WsTransportHardeningHarness::validateHandshake(
            "HTTP/1.1 101 Switching Protocols\r\n" .
            "Connection: Upgrade\r\n" .
            'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n",
            $key
        );
    }, 'Handshake must require Upgrade: websocket');

    $assertThrows(ProtocolException::class, static function () use ($key, $accept): void {
        WsTransportHardeningHarness::validateHandshake(
            "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: keep-alive\r\n" .
            'Sec-WebSocket-Accept: ' . $accept . "\r\n\r\n",
            $key
        );
    }, 'Handshake must require Connection token Upgrade');

    list($fragmented) = WsTransportHardeningHarness::transportWithInput(
        WsTransportHardeningHarness::serverFrame(0x1, 'hel', false) .
        WsTransportHardeningHarness::serverFrame(0x0, 'lo', true)
    );
    $assertSame('hello', $fragmented->recvMessage(1.0), 'Valid fragmented text message must be reassembled');

    list($negativeTimeout) = WsTransportHardeningHarness::transportWithInput(
        WsTransportHardeningHarness::serverFrame(0x1, 'ok')
    );
    $assertSame('ok', $negativeTimeout->recvMessage(-5.0), 'WebSocket recvMessage must normalize direct negative read timeouts');

    list($splitUtf8) = WsTransportHardeningHarness::transportWithInput(
        WsTransportHardeningHarness::serverFrame(0x1, "\xD0", false) .
        WsTransportHardeningHarness::serverFrame(0x0, "\x9F", true)
    );
    $assertSame("\xD0\x9F", $splitUtf8->recvMessage(1.0), 'Valid UTF-8 split across fragments must be accepted after message reassembly');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x1, "\xC3\x28")
        );
        $transport->recvMessage(1.0);
    }, 'Invalid UTF-8 WebSocket text messages must fail before JSON protocol decode');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::maskedServerFrame(0x1, 'no')
        );
        $transport->recvMessage(1.0);
    }, 'Server WebSocket frames must not be masked');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x9, 'ping', false)
        );
        $transport->recvMessage(1.0);
    }, 'Control frames must not be fragmented');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::extendedLengthFrame(0x9, 126, str_repeat('x', 126))
        );
        $transport->recvMessage(1.0);
    }, 'Control frames must stay within 125 bytes');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x0, 'dangling')
        );
        $transport->recvMessage(1.0);
    }, 'Continuation frame without an active fragmented message must fail');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x1, 'half', false) .
            WsTransportHardeningHarness::serverFrame(0x1, 'new', true)
        );
        $transport->recvMessage(1.0);
    }, 'New data frame before fragmented message completion must fail');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x2, '{"opcode":1}')
        );
        $transport->recvMessage(1.0);
    }, 'Binary WebSocket messages must fail because Max WebSocket protocol is JSON text');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::serverFrame(0x1, 'rsv', true, 0x40)
        );
        $transport->recvMessage(1.0);
    }, 'Reserved bits must fail when no WebSocket extensions are negotiated');

    $assertThrows(ProtocolException::class, static function (): void {
        list($transport) = WsTransportHardeningHarness::transportWithInput(
            WsTransportHardeningHarness::extendedLengthFrame(0x1, 16777217)
        );
        $transport->recvMessage(1.0);
    }, 'Oversized WebSocket frames must fail before reading payload bytes');
};
