<?php

declare(strict_types=1);

use PHPMax\Api\Uploads\NativeHttpUploader;
use PHPMax\Config\ClientOptions;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Exception\ProtocolException;
use PHPMax\Transport\ProxyConfig;
use PHPMax\Transport\ProxyConnector;
use PHPMax\Transport\TcpTransport;
use PHPMax\Transport\WebSocketTransport;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $privateFloat = static function ($object, string $property): float {
        $reflection = new ReflectionProperty(get_class($object), $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    };

    $assertSame(null, ProxyConfig::fromUrl(null));
    $assertSame(null, ProxyConfig::fromUrl(''));

    $http = ProxyConfig::fromUrl('http://user:pass@proxy.local:3128');
    $assertSame('http', $http->scheme());
    $assertSame('proxy.local', $http->host());
    $assertSame(3128, $http->port());
    $assertSame('proxy.local:3128', $http->authority());
    $assertSame('tcp://proxy.local:3128', $http->streamTarget());
    $assertSame('Proxy-Authorization: Basic ' . base64_encode('user:pass'), $http->basicAuthorizationHeader());
    $assertSame('http://proxy.local:3128', $http->curlProxyUrl());
    $assertSame('user:pass', $http->curlUserPassword());
    $assert($http->isHttpConnect(), 'HTTP proxy must use CONNECT for TCP/WebSocket');

    $https = ProxyConfig::fromUrl('https://proxy.local');
    $assertSame(443, $https->port());
    $assertSame('tls://proxy.local:443', $https->streamTarget());

    $socks = ProxyConfig::fromUrl('socks5://u:p@127.0.0.1');
    $assertSame('socks5', $socks->scheme());
    $assertSame(1080, $socks->port());
    $assertSame('u', $socks->username());
    $assertSame('p', $socks->password());
    $assert($socks->isSocks5(), 'SOCKS5 proxy must be recognized');

    $options = new ClientOptions(['proxy' => 'http://proxy.local:8080']);
    $assertSame('http://proxy.local:8080', $options->proxy);

    $assertThrows(PHPMaxException::class, static function (): void {
        new ClientOptions(['host' => '']);
    }, 'Empty API host must fail fast');

    $assertThrows(PHPMaxException::class, static function (): void {
        new ClientOptions(['port' => 0]);
    }, 'Zero API port must fail fast');

    $assertThrows(PHPMaxException::class, static function (): void {
        new ClientOptions(['port' => 70000]);
    }, 'Out-of-range API port must fail fast');

    $assertThrows(ProtocolException::class, static function (): void {
        ProxyConfig::fromUrl('ftp://proxy.local:21');
    }, 'Unsupported proxy scheme must fail fast');

    $assertThrows(ProtocolException::class, static function (): void {
        new TcpTransport('', 443);
    }, 'TCP transport must reject empty host');

    $assertThrows(ProtocolException::class, static function (): void {
        new TcpTransport('api.oneme.ru', 0);
    }, 'TCP transport must reject invalid port');

    $assertThrows(ProtocolException::class, static function (): void {
        new TcpTransport('api.oneme.ru', 443, true, 1.0, 'ftp://proxy.local:21');
    }, 'TCP transport must validate proxy URL on construction');

    $assertThrows(ProtocolException::class, static function (): void {
        (new WebSocketTransport('ws://example.test:0/websocket'))->connect();
    }, 'WebSocket transport must reject invalid URL port before connecting');

    $assertThrows(ProtocolException::class, static function (): void {
        new WebSocketTransport('wss://ws-api.oneme.ru/websocket', 1.0, 'https://web.max.ru', 'ftp://proxy.local:21');
    }, 'WebSocket transport must validate proxy URL on construction');

    $assertThrows(ProtocolException::class, static function () use ($http): void {
        (new ProxyConnector($http))->connect('', 443, false, 1.0);
    }, 'Proxy connector must reject empty target host before connecting');

    $assertThrows(ProtocolException::class, static function () use ($http): void {
        (new ProxyConnector($http))->connect('api.oneme.ru', 0, false, 1.0);
    }, 'Proxy connector must reject invalid target port before connecting');

    $assertThrows(ProtocolException::class, static function (): void {
        new NativeHttpUploader(1.0, 'ftp://proxy.local:21');
    }, 'HTTP uploader must validate proxy URL on construction');

    $assertSame(0.001, $privateFloat(new TcpTransport('api.oneme.ru', 443, true, -5.0), 'connectTimeout'));
    $assertSame(0.001, $privateFloat(new WebSocketTransport('wss://ws-api.oneme.ru/websocket', -5.0), 'connectTimeout'));
    $assertSame(0.001, $privateFloat(new NativeHttpUploader(-5.0), 'timeout'));

    if (function_exists('stream_socket_pair')) {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if ($pair !== false) {
            fwrite($pair[1], 'ok');
            $transport = new TcpTransport('api.oneme.ru', 443);
            $streamProperty = new ReflectionProperty(TcpTransport::class, 'stream');
            $streamProperty->setAccessible(true);
            $streamProperty->setValue($transport, $pair[0]);
            $assertSame('ok', $transport->recv(2, -5.0), 'TCP recv must normalize direct negative read timeouts');
            fclose($pair[1]);
            $transport->close();
        } else {
            $assert(true, 'stream_socket_pair unavailable for direct TCP recv timeout fixture');
        }
    } else {
        $assert(true, 'stream_socket_pair unavailable for direct TCP recv timeout fixture');
    }
};
