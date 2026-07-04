<?php

declare(strict_types=1);

namespace PHPMax\Transport;

use PHPMax\Exception\ProtocolException;

class ProxyConfig
{
    /** @var string */
    private $scheme;
    /** @var string */
    private $host;
    /** @var int */
    private $port;
    /** @var string|null */
    private $username;
    /** @var string|null */
    private $password;

    public function __construct(string $scheme, string $host, int $port, ?string $username = null, ?string $password = null)
    {
        $scheme = strtolower($scheme);
        if (!in_array($scheme, ['http', 'https', 'socks5', 'socks5h'], true)) {
            throw new ProtocolException('Unsupported proxy scheme: ' . $scheme);
        }
        if ($host === '' || $port <= 0 || $port > 65535) {
            throw new ProtocolException('Invalid proxy host or port');
        }

        $this->scheme = $scheme;
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public static function fromUrl(?string $url): ?self
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new ProtocolException('Invalid proxy URL');
        }

        $scheme = strtolower((string) $parts['scheme']);
        $port = isset($parts['port']) ? (int) $parts['port'] : self::defaultPort($scheme);
        $username = isset($parts['user']) ? rawurldecode((string) $parts['user']) : null;
        $password = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null;

        return new self($scheme, (string) $parts['host'], $port, $username, $password);
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    public function authority(): string
    {
        return $this->host . ':' . $this->port;
    }

    public function streamTarget(): string
    {
        return ($this->scheme === 'https' ? 'tls' : 'tcp') . '://' . $this->authority();
    }

    public function basicAuthorizationHeader(): ?string
    {
        if ($this->username === null) {
            return null;
        }

        return 'Proxy-Authorization: Basic ' . base64_encode($this->username . ':' . (string) $this->password);
    }

    public function curlType(): int
    {
        if ($this->scheme === 'socks5' || $this->scheme === 'socks5h') {
            return defined('CURLPROXY_SOCKS5_HOSTNAME') ? CURLPROXY_SOCKS5_HOSTNAME : 7;
        }

        return defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0;
    }

    public function curlProxyUrl(): string
    {
        return $this->scheme . '://' . $this->authority();
    }

    public function curlUserPassword(): ?string
    {
        if ($this->username === null) {
            return null;
        }

        return $this->username . ':' . (string) $this->password;
    }

    public function isHttpConnect(): bool
    {
        return $this->scheme === 'http' || $this->scheme === 'https';
    }

    public function isSocks5(): bool
    {
        return $this->scheme === 'socks5' || $this->scheme === 'socks5h';
    }

    private static function defaultPort(string $scheme): int
    {
        if ($scheme === 'http') {
            return 8080;
        }
        if ($scheme === 'https') {
            return 443;
        }
        if ($scheme === 'socks5' || $scheme === 'socks5h') {
            return 1080;
        }

        throw new ProtocolException('Unsupported proxy scheme: ' . $scheme);
    }
}
