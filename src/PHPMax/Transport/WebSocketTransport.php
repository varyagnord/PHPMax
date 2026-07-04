<?php

declare(strict_types=1);

namespace PHPMax\Transport;

use PHPMax\Exception\ProtocolException;

class WebSocketTransport implements TransportInterface, MessageTransportInterface
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    private const MAX_FRAME_PAYLOAD_BYTES = 16777216;

    /** @var string */
    private $url;
    /** @var float */
    private $connectTimeout;
    /** @var string */
    private $origin;
    /** @var ProxyConfig|null */
    private $proxy;
    /** @var resource|null */
    private $stream;
    /** @var string|null */
    private $host;

    public function __construct(string $url = 'wss://ws-api.oneme.ru/websocket', float $connectTimeout = 30.0, string $origin = 'https://web.max.ru', ?string $proxy = null)
    {
        $this->url = $url;
        $this->connectTimeout = $this->normalizeTimeout($connectTimeout);
        $this->origin = $origin;
        $this->proxy = ProxyConfig::fromUrl($proxy);
        $this->stream = null;
        $this->host = null;
    }

    public function connect(): void
    {
        if ($this->connected()) {
            return;
        }

        $parts = parse_url($this->url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new ProtocolException('Invalid WebSocket URL: ' . $this->url);
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'ws' && $scheme !== 'wss') {
            throw new ProtocolException('Unsupported WebSocket URL scheme: ' . $scheme);
        }

        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'wss' ? 443 : 80);
        $this->assertEndpoint($host, $port);
        $path = isset($parts['path']) && $parts['path'] !== '' ? (string) $parts['path'] : '/';
        if (isset($parts['query']) && $parts['query'] !== '') {
            $path .= '?' . $parts['query'];
        }

        if ($this->proxy !== null) {
            $stream = (new ProxyConnector($this->proxy))->connect($host, $port, $scheme === 'wss', $this->connectTimeout);
        } else {
            $contextOptions = [];
            if ($scheme === 'wss') {
                $contextOptions['ssl'] = [
                    'SNI_enabled' => true,
                    'peer_name' => $host,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ];
            }

            $target = sprintf('%s://%s:%d', $scheme === 'wss' ? 'tls' : 'tcp', $host, $port);
            $errno = 0;
            $errstr = '';
            $stream = @stream_socket_client(
                $target,
                $errno,
                $errstr,
                $this->connectTimeout,
                STREAM_CLIENT_CONNECT,
                stream_context_create($contextOptions)
            );
            if (!is_resource($stream)) {
                throw new ProtocolException(sprintf('Failed to connect to %s: [%d] %s', $target, $errno, $errstr));
            }
        }

        stream_set_blocking($stream, true);
        $this->stream = $stream;
        $this->host = $host;

        $this->handshake($path, $host, $port, $scheme === 'wss');
    }

    public function close(): void
    {
        if ($this->connected()) {
            try {
                $this->sendFrame('', 0x8);
            } catch (ProtocolException $e) {
            }
        }
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
        $this->host = null;
    }

    public function send(string $data): void
    {
        $this->sendFrame($data, 0x1);
    }

    public function recv(int $length, float $timeout): string
    {
        return $this->recvMessage($timeout);
    }

    public function recvMessage(float $timeout): string
    {
        $timeout = $this->normalizeTimeout($timeout);
        $message = '';
        $started = false;

        while (true) {
            $frame = $this->readFrame($timeout);
            $opcode = $frame['opcode'];

            if ($opcode === 0x8) {
                $this->close();
                throw new ProtocolException('WebSocket transport closed by peer');
            }
            if ($opcode === 0x9) {
                $this->sendFrame($frame['payload'], 0xA);
                continue;
            }
            if ($opcode === 0xA) {
                continue;
            }
            if ($opcode === 0x2) {
                throw new ProtocolException('Binary WebSocket messages are not supported');
            }
            if ($opcode === 0x1) {
                if ($started) {
                    throw new ProtocolException('Unexpected WebSocket data frame before fragmented message is complete');
                }

                $message = $frame['payload'];
                if ($frame['fin']) {
                    return $this->requireUtf8TextMessage($message);
                }

                $started = true;
                continue;
            }
            if ($opcode === 0x0) {
                if (!$started) {
                    throw new ProtocolException('Unexpected WebSocket continuation frame');
                }

                $message .= $frame['payload'];
                if ($frame['fin']) {
                    return $this->requireUtf8TextMessage($message);
                }
                continue;
            }
        }
    }

    public function connected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    private function handshake(string $path, string $host, int $port, bool $secure): void
    {
        $key = base64_encode(random_bytes(16));
        $defaultPort = $secure ? 443 : 80;
        $hostHeader = $port === $defaultPort ? $host : $host . ':' . $port;
        $request = implode("\r\n", [
            'GET ' . $path . ' HTTP/1.1',
            'Host: ' . $hostHeader,
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: ' . $key,
            'Sec-WebSocket-Version: 13',
            'Origin: ' . $this->origin,
            '',
            '',
        ]);

        $this->writeAll($request);
        $this->validateHandshakeResponse($this->readHttpHeaders($this->connectTimeout), $key);
    }

    private function validateHandshakeResponse(string $headers, string $key): void
    {
        $lines = preg_split('/\r\n/', trim($headers));
        if ($lines === false || $lines === [] || !preg_match('/^HTTP\/1\.[01]\s+101(?:\s|$)/', $lines[0])) {
            throw new ProtocolException('Invalid WebSocket handshake response');
        }

        $parsed = [];
        foreach (array_slice($lines, 1) as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            $parsed[$name] = isset($parsed[$name]) ? $parsed[$name] . ', ' . $value : $value;
        }

        if (strtolower($parsed['upgrade'] ?? '') !== 'websocket') {
            throw new ProtocolException('Invalid WebSocket upgrade header');
        }

        if (!$this->headerContainsToken($parsed['connection'] ?? '', 'upgrade')) {
            throw new ProtocolException('Invalid WebSocket connection header');
        }

        $expected = base64_encode(sha1($key . self::GUID, true));
        $actual = $parsed['sec-websocket-accept'] ?? '';
        if (!hash_equals($expected, $actual)) {
            throw new ProtocolException('Invalid WebSocket accept key');
        }
    }

    private function headerContainsToken(string $header, string $token): bool
    {
        foreach (explode(',', $header) as $part) {
            if (strcasecmp(trim($part), $token) === 0) {
                return true;
            }
        }

        return false;
    }

    private function sendFrame(string $payload, int $opcode): void
    {
        $length = strlen($payload);
        if (!$this->isKnownOpcode($opcode) || $opcode === 0x0) {
            throw new ProtocolException('Unsupported WebSocket opcode: ' . $opcode);
        }
        if ($this->isControlOpcode($opcode) && $length > 125) {
            throw new ProtocolException('WebSocket control frame payload is too large');
        }

        $header = chr(0x80 | ($opcode & 0x0F));
        if ($length <= 125) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(0x80 | 126) . pack('n', $length);
        } else {
            $header .= chr(0x80 | 127) . $this->packUInt64($length);
        }

        $mask = random_bytes(4);
        $this->writeAll($header . $mask . $this->applyMask($payload, $mask));
    }

    /**
     * @return array{fin: bool, opcode: int, payload: string}
     */
    private function readFrame(float $timeout): array
    {
        $head = $this->readExact(2, $timeout);
        $first = ord($head[0]);
        $second = ord($head[1]);
        $fin = ($first & 0x80) !== 0;
        if (($first & 0x70) !== 0) {
            throw new ProtocolException('WebSocket reserved bits are not supported');
        }

        $opcode = $first & 0x0F;
        if (!$this->isKnownOpcode($opcode)) {
            throw new ProtocolException('Unsupported WebSocket opcode: ' . $opcode);
        }

        $masked = ($second & 0x80) !== 0;
        if ($masked) {
            throw new ProtocolException('Masked WebSocket frames from server are not allowed');
        }

        $lengthCode = $second & 0x7F;
        $length = $lengthCode;

        if ($lengthCode === 126) {
            $parts = unpack('nlen', $this->readExact(2, $timeout));
            $length = (int) $parts['len'];
            if ($length < 126) {
                throw new ProtocolException('WebSocket frame uses non-minimal payload length encoding');
            }
        } elseif ($lengthCode === 127) {
            $parts = unpack('Nhigh/Nlow', $this->readExact(8, $timeout));
            $high = (int) $parts['high'];
            $low = (int) $parts['low'];
            if ($high > 0x7FFFFFFF) {
                throw new ProtocolException('WebSocket frame too large for PHP integer range');
            }
            $length = $high * 4294967296 + $low;
            if ($length <= 0xFFFF) {
                throw new ProtocolException('WebSocket frame uses non-minimal payload length encoding');
            }
        }

        if ($this->isControlOpcode($opcode)) {
            if (!$fin) {
                throw new ProtocolException('WebSocket control frames must not be fragmented');
            }
            if ($length > 125) {
                throw new ProtocolException('WebSocket control frame payload is too large');
            }
        }

        if ($length > self::MAX_FRAME_PAYLOAD_BYTES) {
            throw new ProtocolException('WebSocket frame payload is too large');
        }

        $payload = $length > 0 ? $this->readExact($length, $timeout) : '';

        return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
    }

    private function isKnownOpcode(int $opcode): bool
    {
        return $opcode === 0x0
            || $opcode === 0x1
            || $opcode === 0x2
            || $opcode === 0x8
            || $opcode === 0x9
            || $opcode === 0xA;
    }

    private function isControlOpcode(int $opcode): bool
    {
        return $opcode >= 0x8;
    }

    private function requireUtf8TextMessage(string $message): string
    {
        if ($message === '' || preg_match('//u', $message) === 1) {
            return $message;
        }

        throw new ProtocolException('WebSocket text message is not valid UTF-8');
    }

    private function readHttpHeaders(float $timeout): string
    {
        $headers = '';
        while (strpos($headers, "\r\n\r\n") === false) {
            $headers .= $this->readExact(1, $timeout);
            if (strlen($headers) > 16384) {
                throw new ProtocolException('WebSocket handshake headers are too large');
            }
        }

        return $headers;
    }

    private function readExact(int $length, float $timeout): string
    {
        if ($length < 0) {
            throw new ProtocolException('WebSocket read length must not be negative');
        }
        if ($length === 0) {
            return '';
        }

        $stream = $this->requireStream();
        $timeout = $this->normalizeTimeout($timeout);
        $seconds = (int) floor($timeout);
        $microseconds = (int) max(0, ($timeout - $seconds) * 1000000);
        stream_set_timeout($stream, $seconds, $microseconds);

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($stream, $length - strlen($data));
            if ($chunk === false) {
                throw new ProtocolException('Failed to read from WebSocket transport');
            }
            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new ProtocolException('Timed out reading from WebSocket transport');
                }
                if (feof($stream)) {
                    throw new ProtocolException('WebSocket transport closed by peer');
                }
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }

    private function writeAll(string $data): void
    {
        $stream = $this->requireStream();
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new ProtocolException('Failed to write to WebSocket transport');
            }
            $written += $chunk;
        }
    }

    private function applyMask(string $payload, string $mask): string
    {
        $out = '';
        $length = strlen($payload);
        for ($i = 0; $i < $length; $i++) {
            $out .= $payload[$i] ^ $mask[$i % 4];
        }

        return $out;
    }

    private function packUInt64(int $value): string
    {
        $high = intdiv($value, 4294967296);
        $low = $value % 4294967296;

        return pack('N2', $high, $low);
    }

    /**
     * @return resource
     */
    private function requireStream()
    {
        if (!$this->connected()) {
            throw new ProtocolException('WebSocket transport is not connected');
        }

        return $this->stream;
    }

    private function assertEndpoint(string $host, int $port): void
    {
        if (trim($host) === '' || $port <= 0 || $port > 65535) {
            throw new ProtocolException('Invalid WebSocket transport host or port');
        }
    }

    private function normalizeTimeout(float $timeout): float
    {
        return max(0.001, $timeout);
    }
}
