<?php

declare(strict_types=1);

namespace PHPMax\Transport;

use PHPMax\Exception\ProtocolException;

class TcpTransport implements TransportInterface
{
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var bool */
    private $useSsl;
    /** @var float */
    private $connectTimeout;
    /** @var ProxyConfig|null */
    private $proxy;
    /** @var resource|null */
    private $stream;

    public function __construct(string $host = 'api.oneme.ru', int $port = 443, bool $useSsl = true, float $connectTimeout = 30.0, ?string $proxy = null)
    {
        $this->assertEndpoint($host, $port);

        $this->host = $host;
        $this->port = $port;
        $this->useSsl = $useSsl;
        $this->connectTimeout = $this->normalizeTimeout($connectTimeout);
        $this->proxy = ProxyConfig::fromUrl($proxy);
        $this->stream = null;
    }

    public function connect(): void
    {
        if ($this->connected()) {
            return;
        }

        if ($this->proxy !== null) {
            $stream = (new ProxyConnector($this->proxy))->connect($this->host, $this->port, $this->useSsl, $this->connectTimeout);
        } else {
            $scheme = $this->useSsl ? 'tls' : 'tcp';
            $target = sprintf('%s://%s:%d', $scheme, $this->host, $this->port);
            $errno = 0;
            $errstr = '';
            $context = stream_context_create([
                'ssl' => [
                    'SNI_enabled' => true,
                    'peer_name' => $this->host,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $stream = @stream_socket_client($target, $errno, $errstr, $this->connectTimeout, STREAM_CLIENT_CONNECT, $context);
            if (!is_resource($stream)) {
                throw new ProtocolException(sprintf('Failed to connect to %s: [%d] %s', $target, $errno, $errstr));
            }
        }

        stream_set_blocking($stream, true);
        $this->stream = $stream;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    public function send(string $data): void
    {
        $stream = $this->requireStream();
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new ProtocolException('Failed to write to TCP transport');
            }
            $written += $chunk;
        }
    }

    public function recv(int $length, float $timeout): string
    {
        if ($length < 0) {
            throw new ProtocolException('TCP recv length must not be negative');
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
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new ProtocolException('Timed out reading from TCP transport');
                }
                throw new ProtocolException('Failed to read from TCP transport');
            }
            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new ProtocolException('Timed out reading from TCP transport');
                }
                if (feof($stream)) {
                    throw new ProtocolException('TCP transport closed by peer');
                }
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }

    public function connected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    /**
     * @return resource
     */
    private function requireStream()
    {
        if (!$this->connected()) {
            throw new ProtocolException('TCP transport is not connected');
        }

        return $this->stream;
    }

    private function assertEndpoint(string $host, int $port): void
    {
        if (trim($host) === '' || $port <= 0 || $port > 65535) {
            throw new ProtocolException('Invalid TCP transport host or port');
        }
    }

    private function normalizeTimeout(float $timeout): float
    {
        return max(0.001, $timeout);
    }
}
