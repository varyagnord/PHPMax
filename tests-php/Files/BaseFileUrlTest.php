<?php

declare(strict_types=1);

use PHPMax\Exception\UploadException;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;

final class BaseFileUrlLoopbackServer
{
    /**
     * @return array{0: string, 1: int}
     */
    public static function start(string $expectedMethod, string $path, int $status, string $body, array $headers = []): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($server)) {
            throw new RuntimeException('Failed to create URL source test server: ' . $errstr);
        }

        $address = (string) stream_socket_get_name($server, false);
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            throw new RuntimeException('Failed to fork URL source test server');
        }

        if ($pid === 0) {
            self::serveOnce($server, $expectedMethod, $path, $status, $body, $headers);
            exit(0);
        }

        fclose($server);

        return ['http://' . $address . $path, $pid];
    }

    /**
     * @param array<string, string> $headers
     */
    private static function serveOnce($server, string $expectedMethod, string $path, int $status, string $body, array $headers): void
    {
        $conn = @stream_socket_accept($server, 5.0);
        fclose($server);
        if (!is_resource($conn)) {
            exit(2);
        }

        stream_set_timeout($conn, 5);
        $request = '';
        while (strpos($request, "\r\n\r\n") === false && !feof($conn)) {
            $chunk = fread($conn, 1);
            if ($chunk === false) {
                fclose($conn);
                exit(3);
            }
            $request .= $chunk;
            if (strlen($request) > 16384) {
                fclose($conn);
                exit(4);
            }
        }

        $requestLine = strtok($request, "\r\n");
        if ($requestLine !== $expectedMethod . ' ' . $path . ' HTTP/1.1') {
            fclose($conn);
            exit(5);
        }

        $reason = $status >= 200 && $status < 300 ? 'OK' : 'Not Found';
        $responseHeaders = [
            'Content-Length' => (string) strlen($body),
            'Connection' => 'close',
        ];
        foreach ($headers as $name => $value) {
            $responseHeaders[$name] = $value;
        }

        $response = 'HTTP/1.1 ' . $status . ' ' . $reason . "\r\n";
        foreach ($responseHeaders as $name => $value) {
            $response .= $name . ': ' . $value . "\r\n";
        }
        $response .= "\r\n";
        if ($expectedMethod !== 'HEAD') {
            $response .= $body;
        }

        fwrite($conn, $response);
        fclose($conn);
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $assertSame('report.pdf', File::fromUrl('https://cdn.example.test/files/report.pdf')->name());
    $assertSame('clip.mp4', Video::fromUrl('http://cdn.example.test/video/clip.mp4')->name());
    $assertSame(['jpg', 'image/jpeg'], Photo::fromUrl('https://cdn.example.test/img/avatar.jpg?token=1')->validatePhoto());

    foreach ([
        'file:///tmp/secret.pdf',
        'php://filter/resource=/etc/passwd',
        'ftp://cdn.example.test/file.pdf',
        '//cdn.example.test/file.pdf',
        '/relative/file.pdf',
        'https:///missing-host.pdf',
        'http://cdn.example.test:0/file.pdf',
    ] as $unsafeUrl) {
        $assertThrows(UploadException::class, static function () use ($unsafeUrl): void {
            File::fromUrl($unsafeUrl);
        }, 'URL sources must reject non-HTTP(S), relative or hostless URL: ' . $unsafeUrl);
    }

    if (!function_exists('pcntl_fork') || !function_exists('stream_socket_server')) {
        $assert(true, 'BaseFile URL tests require pcntl and sockets');
        return;
    }

    $withServer = static function (string $method, string $path, int $status, string $body, callable $callback, array $headers = []) use ($assertSame): void {
        list($url, $pid) = BaseFileUrlLoopbackServer::start($method, $path, $status, $body, $headers);
        try {
            $callback($url);
        } finally {
            pcntl_waitpid($pid, $serverStatus);
        }
        $assertSame(0, pcntl_wexitstatus($serverStatus), 'URL source loopback server must receive expected request');
    };

    $withServer('GET', '/read.txt', 200, 'url-bytes', static function (string $url) use ($assertSame): void {
        $assertSame('url-bytes', File::fromUrl($url)->read());
    });

    $withServer('HEAD', '/size.bin', 200, '', static function (string $url) use ($assertSame): void {
        $assertSame(12, File::fromUrl($url)->size());
    }, ['Content-Length' => '12']);

    $withServer('GET', '/chunks.bin', 200, 'abcdef', static function (string $url) use ($assertSame): void {
        $assertSame(['ab', 'cd', 'ef'], iterator_to_array(File::fromUrl($url)->iterChunks(2)));
    });

    $withServer('GET', '/missing-read.txt', 404, 'missing', static function (string $url) use ($assertThrows): void {
        $assertThrows(UploadException::class, static function () use ($url): void {
            File::fromUrl($url)->read();
        }, 'URL read must fail on HTTP 404 like PyMax raise_for_status');
    });

    $withServer('GET', '/secret-read.txt?token=secret-query', 404, 'missing', static function (string $url) use ($assert): void {
        $secretUrl = str_replace('http://', 'http://user:secret-pass@', $url);
        try {
            File::fromUrl($secretUrl)->read();
            throw new RuntimeException('Expected URL read to fail');
        } catch (UploadException $e) {
            $message = $e->getMessage();
            $assert(strpos($message, 'HTTP 404') !== false, 'URL read error must keep HTTP status');
            $assert(strpos($message, 'secret-query') === false, 'URL read error must redact query secrets');
            $assert(strpos($message, 'secret-pass') === false, 'URL read error must redact userinfo secrets');
            $assert(strpos($message, 'user:') === false, 'URL read error must not include URL userinfo');
        }
    });

    $withServer('HEAD', '/missing-size.bin', 404, '', static function (string $url) use ($assertThrows): void {
        $assertThrows(UploadException::class, static function () use ($url): void {
            File::fromUrl($url)->size();
        }, 'URL size must fail on HTTP 404 even when Content-Length exists');
    }, ['Content-Length' => '7']);

    $withServer('GET', '/missing-chunks.bin', 404, 'missing', static function (string $url) use ($assertThrows): void {
        $assertThrows(UploadException::class, static function () use ($url): void {
            iterator_to_array(File::fromUrl($url)->iterChunks(2));
        }, 'URL chunk iteration must fail on HTTP 404 like PyMax raise_for_status');
    });
};
