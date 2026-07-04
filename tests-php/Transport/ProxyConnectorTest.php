<?php

declare(strict_types=1);

use PHPMax\Transport\ProxyConfig;
use PHPMax\Transport\ProxyConnector;

final class ProxyConnectorLoopbackServer
{
    /**
     * @return array{0: string, 1: int}
     */
    public static function startHttpConnect(string $expectedTarget, ?string $expectedAuth, int $responseDelayMicros = 0): array
    {
        return self::start(static function ($conn) use ($expectedTarget, $expectedAuth, $responseDelayMicros): int {
            $headers = self::readHeaders($conn);
            $lines = preg_split('/\r\n/', trim($headers));
            if ($lines === false || ($lines[0] ?? '') !== 'CONNECT ' . $expectedTarget . ' HTTP/1.1') {
                return 10;
            }
            if (stripos($headers, "\r\nHost: " . $expectedTarget . "\r\n") === false) {
                return 11;
            }
            if ($expectedAuth !== null && stripos($headers, "\r\nProxy-Authorization: " . $expectedAuth . "\r\n") === false) {
                return 12;
            }

            if ($responseDelayMicros > 0) {
                usleep($responseDelayMicros);
            }
            self::writeAll($conn, "HTTP/1.1 200 Connection Established\r\nProxy-Agent: PHPMaxTest\r\n\r\n");

            return self::echoTunnel($conn);
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    public static function startSocks5(string $expectedHost, int $expectedPort, ?string $expectedUser, ?string $expectedPassword): array
    {
        return self::start(static function ($conn) use ($expectedHost, $expectedPort, $expectedUser, $expectedPassword): int {
            $greeting = self::readExact($conn, 2);
            if ($greeting[0] !== "\x05") {
                return 20;
            }
            $methodCount = ord($greeting[1]);
            $methods = self::readExact($conn, $methodCount);
            $wantAuth = $expectedUser !== null;
            if ($wantAuth) {
                if (strpos($methods, "\x02") === false) {
                    return 21;
                }
                self::writeAll($conn, "\x05\x02");
                $authHead = self::readExact($conn, 2);
                if ($authHead[0] !== "\x01") {
                    return 22;
                }
                $user = self::readExact($conn, ord($authHead[1]));
                $passLen = ord(self::readExact($conn, 1));
                $password = self::readExact($conn, $passLen);
                if ($user !== (string) $expectedUser || $password !== (string) $expectedPassword) {
                    return 23;
                }
                self::writeAll($conn, "\x01\x00");
            } else {
                if (strpos($methods, "\x00") === false) {
                    return 24;
                }
                self::writeAll($conn, "\x05\x00");
            }

            $requestHead = self::readExact($conn, 5);
            if ($requestHead[0] !== "\x05" || $requestHead[1] !== "\x01" || $requestHead[2] !== "\x00" || $requestHead[3] !== "\x03") {
                return 25;
            }
            $hostLength = ord($requestHead[4]);
            $host = self::readExact($conn, $hostLength);
            $port = unpack('nport', self::readExact($conn, 2));
            if ($host !== $expectedHost || (int) $port['port'] !== $expectedPort) {
                return 26;
            }

            self::writeAll($conn, "\x05\x00\x00\x01\x00\x00\x00\x00\x00\x00");

            return self::echoTunnel($conn);
        });
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function start(callable $handler): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($server)) {
            throw new RuntimeException('Failed to create proxy loopback server: ' . $errstr);
        }

        $address = (string) stream_socket_get_name($server, false);
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            throw new RuntimeException('Failed to fork proxy loopback server');
        }

        if ($pid === 0) {
            $conn = @stream_socket_accept($server, 5.0);
            fclose($server);
            if (!is_resource($conn)) {
                exit(2);
            }
            stream_set_timeout($conn, 5);
            $status = (int) call_user_func($handler, $conn);
            fclose($conn);
            exit($status);
        }

        fclose($server);

        return [$address, $pid];
    }

    private static function echoTunnel($conn): int
    {
        $payload = self::readExact($conn, 4);
        if ($payload !== 'ping') {
            return 30;
        }
        self::writeAll($conn, 'pong');

        return 0;
    }

    private static function readHeaders($conn): string
    {
        $headers = '';
        while (strpos($headers, "\r\n\r\n") === false) {
            $headers .= self::readExact($conn, 1);
            if (strlen($headers) > 16384) {
                return '';
            }
        }

        return $headers;
    }

    private static function readExact($conn, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($conn, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                return $data;
            }
            $data .= $chunk;
        }

        return $data;
    }

    private static function writeAll($conn, string $data): void
    {
        $written = 0;
        $length = strlen($data);
        while ($written < $length) {
            $chunk = fwrite($conn, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                return;
            }
            $written += $chunk;
        }
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    if (!function_exists('pcntl_fork') || !function_exists('stream_socket_server')) {
        $assert(true, 'ProxyConnector loopback tests require pcntl and sockets');
        return;
    }

    $exerciseTunnel = static function (string $proxyUrl, string $targetHost, int $targetPort, int $pid, float $timeout = 2.0) use ($assertSame): void {
        $stream = (new ProxyConnector(ProxyConfig::fromUrl($proxyUrl)))->connect($targetHost, $targetPort, false, $timeout);
        fwrite($stream, 'ping');
        $reply = fread($stream, 4);
        fclose($stream);
        pcntl_waitpid($pid, $status);
        $assertSame('pong', $reply);
        $assertSame(0, pcntl_wexitstatus($status), 'Proxy loopback server must receive expected handshake and tunnel bytes');
    };

    list($httpAddress, $httpPid) = ProxyConnectorLoopbackServer::startHttpConnect(
        'max.example:443',
        'Basic ' . base64_encode('user:pass'),
        50000
    );
    $exerciseTunnel('http://user:pass@' . $httpAddress, 'max.example', 443, $httpPid, -5.0);

    list($socksAddress, $socksPid) = ProxyConnectorLoopbackServer::startSocks5('max.example', 5228, null, null);
    $exerciseTunnel('socks5://' . $socksAddress, 'max.example', 5228, $socksPid);

    list($socksAuthAddress, $socksAuthPid) = ProxyConnectorLoopbackServer::startSocks5('max.example', 443, 'u', 'p');
    $exerciseTunnel('socks5://u:p@' . $socksAuthAddress, 'max.example', 443, $socksAuthPid);
};
