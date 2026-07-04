<?php

declare(strict_types=1);

namespace PHPMax\Transport;

use PHPMax\Exception\ProtocolException;

class ProxyConnector
{
    /** @var ProxyConfig */
    private $proxy;

    public function __construct(ProxyConfig $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * @return resource
     */
    public function connect(string $targetHost, int $targetPort, bool $targetSsl, float $timeout)
    {
        $this->assertEndpoint($targetHost, $targetPort);
        $timeout = $this->normalizeTimeout($timeout);

        $stream = $this->connectProxy($targetHost, $timeout);

        if ($this->proxy->isHttpConnect()) {
            $this->httpConnect($stream, $targetHost, $targetPort, $timeout);
        } elseif ($this->proxy->isSocks5()) {
            $this->socks5Connect($stream, $targetHost, $targetPort, $timeout);
        } else {
            throw new ProtocolException('Unsupported proxy scheme: ' . $this->proxy->scheme());
        }

        if ($targetSsl) {
            stream_context_set_option($stream, 'ssl', 'SNI_enabled', true);
            stream_context_set_option($stream, 'ssl', 'peer_name', $targetHost);
            stream_context_set_option($stream, 'ssl', 'verify_peer', true);
            stream_context_set_option($stream, 'ssl', 'verify_peer_name', true);
            $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($enabled !== true) {
                throw new ProtocolException('Failed to enable TLS over proxy connection');
            }
        }

        return $stream;
    }

    /**
     * @return resource
     */
    private function connectProxy(string $targetHost, float $timeout)
    {
        $errno = 0;
        $errstr = '';
        $context = stream_context_create([
            'ssl' => [
                'SNI_enabled' => true,
                'peer_name' => $this->proxy->host(),
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $stream = @stream_socket_client($this->proxy->streamTarget(), $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($stream)) {
            throw new ProtocolException(sprintf('Failed to connect to proxy %s: [%d] %s', $this->proxy->authority(), $errno, $errstr));
        }
        stream_set_blocking($stream, true);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function httpConnect($stream, string $targetHost, int $targetPort, float $timeout): void
    {
        $target = $targetHost . ':' . $targetPort;
        $headers = [
            'CONNECT ' . $target . ' HTTP/1.1',
            'Host: ' . $target,
        ];
        $auth = $this->proxy->basicAuthorizationHeader();
        if ($auth !== null) {
            $headers[] = $auth;
        }
        $headers[] = '';
        $headers[] = '';
        $this->writeAll($stream, implode("\r\n", $headers));

        $response = $this->readHeaders($stream, $timeout);
        $firstLine = strtok($response, "\r\n");
        if ($firstLine === false || !preg_match('/^HTTP\/\S+\s+2\d\d\b/', $firstLine)) {
            throw new ProtocolException('HTTP proxy CONNECT failed');
        }
    }

    /**
     * @param resource $stream
     */
    private function socks5Connect($stream, string $targetHost, int $targetPort, float $timeout): void
    {
        $methods = $this->proxy->username() !== null ? "\x00\x02" : "\x00";
        $this->writeAll($stream, "\x05" . chr(strlen($methods)) . $methods);
        $selection = $this->readExact($stream, 2, $timeout);
        if ($selection[0] !== "\x05") {
            throw new ProtocolException('Invalid SOCKS5 proxy greeting');
        }
        $method = ord($selection[1]);
        if ($method === 0x02) {
            $this->socks5Authenticate($stream, $timeout);
        } elseif ($method !== 0x00) {
            throw new ProtocolException('SOCKS5 proxy rejected authentication methods');
        }

        $hostLength = strlen($targetHost);
        if ($hostLength > 255) {
            throw new ProtocolException('SOCKS5 target host is too long');
        }
        $request = "\x05\x01\x00\x03" . chr($hostLength) . $targetHost . pack('n', $targetPort);
        $this->writeAll($stream, $request);
        $head = $this->readExact($stream, 4, $timeout);
        if ($head[0] !== "\x05" || ord($head[1]) !== 0x00) {
            throw new ProtocolException('SOCKS5 proxy CONNECT failed');
        }
        $atype = ord($head[3]);
        if ($atype === 0x01) {
            $this->readExact($stream, 4, $timeout);
        } elseif ($atype === 0x03) {
            $length = ord($this->readExact($stream, 1, $timeout));
            $this->readExact($stream, $length, $timeout);
        } elseif ($atype === 0x04) {
            $this->readExact($stream, 16, $timeout);
        } else {
            throw new ProtocolException('Invalid SOCKS5 response address type');
        }
        $this->readExact($stream, 2, $timeout);
    }

    /**
     * @param resource $stream
     */
    private function socks5Authenticate($stream, float $timeout): void
    {
        $username = (string) $this->proxy->username();
        $password = (string) $this->proxy->password();
        if (strlen($username) > 255 || strlen($password) > 255) {
            throw new ProtocolException('SOCKS5 username/password is too long');
        }
        $this->writeAll($stream, "\x01" . chr(strlen($username)) . $username . chr(strlen($password)) . $password);
        $response = $this->readExact($stream, 2, $timeout);
        if ($response[0] !== "\x01" || $response[1] !== "\x00") {
            throw new ProtocolException('SOCKS5 authentication failed');
        }
    }

    /**
     * @param resource $stream
     */
    private function readHeaders($stream, float $timeout): string
    {
        $headers = '';
        while (strpos($headers, "\r\n\r\n") === false) {
            $headers .= $this->readExact($stream, 1, $timeout);
            if (strlen($headers) > 16384) {
                throw new ProtocolException('Proxy response headers are too large');
            }
        }

        return $headers;
    }

    /**
     * @param resource $stream
     */
    private function readExact($stream, int $length, float $timeout): string
    {
        $timeout = $this->normalizeTimeout($timeout);
        $seconds = (int) floor($timeout);
        $microseconds = (int) max(0, ($timeout - $seconds) * 1000000);
        stream_set_timeout($stream, $seconds, $microseconds);

        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($stream, $length - strlen($data));
            if ($chunk === false) {
                throw new ProtocolException('Failed to read from proxy');
            }
            if ($chunk === '') {
                $meta = stream_get_meta_data($stream);
                if (!empty($meta['timed_out'])) {
                    throw new ProtocolException('Timed out reading from proxy');
                }
                if (feof($stream)) {
                    throw new ProtocolException('Proxy closed connection');
                }
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }

    /**
     * @param resource $stream
     */
    private function writeAll($stream, string $data): void
    {
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new ProtocolException('Failed to write to proxy');
            }
            $written += $chunk;
        }
    }

    private function assertEndpoint(string $host, int $port): void
    {
        if (trim($host) === '' || $port <= 0 || $port > 65535) {
            throw new ProtocolException('Invalid proxy target host or port');
        }
    }

    private function normalizeTimeout(float $timeout): float
    {
        if ($timeout <= 0.0) {
            return 1.0;
        }

        return max(0.001, $timeout);
    }
}
