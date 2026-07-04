<?php

declare(strict_types=1);

use PHPMax\Api\Uploads\NativeHttpUploader;
use PHPMax\Exception\UploadException;

final class NativeHttpUploaderLoopbackServer
{
    /**
     * @return array{0: string, 1: int}
     */
    public static function start(string $expectedMethod, string $expectedPath, ?string $expectedBody, ?callable $inspector = null): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!is_resource($server)) {
            throw new RuntimeException('Failed to create loopback HTTP server: ' . $errstr);
        }

        $address = (string) stream_socket_get_name($server, false);
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            throw new RuntimeException('Failed to fork loopback HTTP server');
        }

        if ($pid === 0) {
            self::serveOnce($server, $expectedMethod, $expectedPath, $expectedBody, $inspector);
            exit(0);
        }

        fclose($server);

        return [$address, $pid];
    }

    private static function serveOnce($server, string $expectedMethod, string $expectedPath, ?string $expectedBody, ?callable $inspector): void
    {
        $conn = @stream_socket_accept($server, 5.0);
        fclose($server);
        if (!is_resource($conn)) {
            exit(2);
        }

        stream_set_timeout($conn, 5);
        $headers = '';
        while (strpos($headers, "\r\n\r\n") === false && !feof($conn)) {
            $chunk = fread($conn, 1);
            if ($chunk === false) {
                fclose($conn);
                exit(3);
            }
            $headers .= $chunk;
            if (strlen($headers) > 16384) {
                fclose($conn);
                exit(4);
            }
        }

        $length = 0;
        if (preg_match('/\r\nContent-Length:\s*(\d+)\r\n/i', $headers, $matches)) {
            $length = (int) $matches[1];
        }

        $body = '';
        while (strlen($body) < $length && !feof($conn)) {
            $chunk = fread($conn, $length - strlen($body));
            if ($chunk === false) {
                fclose($conn);
                exit(5);
            }
            $body .= $chunk;
        }

        fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok");
        fclose($conn);

        $requestLine = strtok($headers, "\r\n");
        $expectedRequestLine = $expectedMethod . ' ' . $expectedPath . ' HTTP/1.1';
        if ($requestLine !== $expectedRequestLine) {
            exit(6);
        }
        if ($expectedBody !== null && $body !== $expectedBody) {
            exit(7);
        }
        if ($expectedBody !== null && $length !== strlen($expectedBody)) {
            exit(8);
        }
        if ($inspector !== null && !call_user_func($inspector, $headers, $body)) {
            exit(9);
        }
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    foreach ([
        'file:///tmp/phpmax-upload-target',
        'ftp://upload.example.test/file',
        '//upload.example.test/file',
        '/relative/upload',
        'http://upload.example.test:0/file',
    ] as $invalidUrl) {
        $assertThrows(UploadException::class, static function () use ($invalidUrl): void {
            (new NativeHttpUploader(5.0))->uploadMultipart($invalidUrl, 'file', 'abc', 'a.txt', 'text/plain');
        }, 'Multipart upload must reject non-HTTP(S) URL before HTTP client starts');

        $assertThrows(UploadException::class, static function () use ($invalidUrl): void {
            (new NativeHttpUploader(5.0))->uploadStream($invalidUrl, [], ['abc'], 3);
        }, 'Streaming upload must reject non-HTTP(S) URL before HTTP client starts');
    }

    if (!function_exists('curl_init') || !function_exists('pcntl_fork') || !function_exists('stream_socket_server')) {
        $assert(true, 'NativeHttpUploader loopback test requires ext-curl, pcntl and sockets');
        return;
    }

    list($address, $pid) = NativeHttpUploaderLoopbackServer::start('POST', '/upload', 'abcdef');

    try {
        $uploader = new NativeHttpUploader(5.0);
        $response = $uploader->uploadStream(
            'http://' . $address . '/upload',
            ['Content-Type' => 'application/octet-stream'],
            ['abc', '', 'def'],
            6
        );
        $assertSame(200, $response->status());
        $assertSame('ok', $response->body());
    } finally {
        pcntl_waitpid($pid, $status);
    }

    $assertSame(0, pcntl_wexitstatus($status), 'Loopback server must receive streaming POST body and Content-Length');

    $multipartInspector = static function (string $headers, string $body): bool {
        if (!preg_match('/\r\nContent-Type:\s*multipart\/form-data;\s*boundary=([^\r\n;]+)/i', $headers, $matches)) {
            return false;
        }

        $boundary = $matches[1];
        if (!preg_match('/\r\nContent-Length:\s*(\d+)\r\n/i', $headers, $lengthMatches)) {
            return false;
        }
        if ((int) $lengthMatches[1] !== strlen($body)) {
            return false;
        }

        return strpos($body, '--' . $boundary . "\r\n") === 0
            && strpos($body, 'Content-Disposition: form-data; name="file\"field"; filename="avatar\"X-Bad: 1.png"') !== false
            && strpos($body, "\r\nX-Bad: 1.png") === false
            && strpos($body, "Content-Type: image/png\r\n\r\nphoto-bytes\r\n") !== false
            && substr($body, -strlen('--' . $boundary . "--\r\n")) === '--' . $boundary . "--\r\n";
    };

    list($multipartAddress, $multipartPid) = NativeHttpUploaderLoopbackServer::start('POST', '/photo', null, $multipartInspector);

    try {
        $uploader = new NativeHttpUploader(5.0);
        $response = $uploader->uploadMultipart(
            'http://' . $multipartAddress . '/photo',
            'file"field',
            'photo-bytes',
            "avatar\"\r\nX-Bad: 1.png",
            'image/png'
        );
        $assertSame(200, $response->status());
        $assertSame('ok', $response->body());
    } finally {
        pcntl_waitpid($multipartPid, $multipartStatus);
    }

    $assertSame(0, pcntl_wexitstatus($multipartStatus), 'Loopback server must receive sanitized multipart upload body');
};
