<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Exception\UploadException;
use PHPMax\Transport\ProxyConfig;

class NativeHttpUploader implements HttpUploaderInterface
{
    /** @var float */
    private $timeout;
    /** @var ProxyConfig|null */
    private $proxy;

    public function __construct(float $timeout = 900.0, ?string $proxy = null)
    {
        $this->timeout = max(0.001, $timeout);
        $this->proxy = ProxyConfig::fromUrl($proxy);
    }

    public function uploadMultipart(
        string $url,
        string $fieldName,
        string $contents,
        string $filename,
        string $contentType
    ): HttpUploadResponse {
        $this->assertHttpUrl($url);

        $boundary = '----PHPMax' . bin2hex(random_bytes(12));
        $body = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $this->escapeHeaderValue($fieldName) . '"; filename="' . $this->escapeHeaderValue($filename) . '"' . "\r\n"
            . 'Content-Type: ' . $contentType . "\r\n\r\n"
            . $contents . "\r\n"
            . '--' . $boundary . "--\r\n";

        return $this->postBody($url, [
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Content-Length' => (string) strlen($body),
        ], $body);
    }

    public function uploadStream(
        string $url,
        array $headers,
        iterable $chunks,
        int $contentLength
    ): HttpUploadResponse {
        $this->assertHttpUrl($url);

        if (!function_exists('curl_init')) {
            throw new UploadException('ext-curl is required for streaming file/video uploads');
        }

        $streamBody = new StreamBody($chunks, $contentLength);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new UploadException('Failed to initialize upload HTTP client');
        }

        curl_setopt_array($ch, [
            CURLOPT_UPLOAD => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => (int) ceil($this->timeout),
            CURLOPT_HTTPHEADER => $this->formatHeaders($this->withContentLength($headers, $contentLength)),
            CURLOPT_INFILESIZE => $contentLength,
            CURLOPT_READFUNCTION => static function ($curl, $file, int $length) use ($streamBody): string {
                return $streamBody->read($length);
            },
        ]);
        if (defined('CURLOPT_INFILESIZE_LARGE')) {
            curl_setopt($ch, CURLOPT_INFILESIZE_LARGE, $contentLength);
        }
        $this->applyCurlProxy($ch);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new UploadException('HTTP upload failed: ' . $error);
        }
        $streamBody->assertComplete();

        return new HttpUploadResponse($status, (string) $body);
    }

    /**
     * @param array<string, string> $headers
     */
    private function postBody(string $url, array $headers, string $body): HttpUploadResponse
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new UploadException('Failed to initialize upload HTTP client');
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => (int) ceil($this->timeout),
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_POSTFIELDS => $body,
            ]);
            $this->applyCurlProxy($ch);

            $responseBody = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($responseBody === false) {
                throw new UploadException('HTTP upload failed: ' . $error);
            }

            return new HttpUploadResponse($status, (string) $responseBody);
        }

        if ($this->proxy !== null) {
            throw new UploadException('ext-curl is required for HTTP uploads through proxy');
        }

        $httpOptions = [
            'method' => 'POST',
            'header' => $this->headersToString($headers),
            'content' => $body,
            'timeout' => $this->timeout,
            'ignore_errors' => true,
        ];

        $context = stream_context_create(['http' => $httpOptions]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new UploadException('HTTP upload failed');
        }

        return new HttpUploadResponse($this->statusFromHeaders($http_response_header ?? []), (string) $responseBody);
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $result[] = $name . ': ' . $value;
        }

        return $result;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function withContentLength(array $headers, int $contentLength): array
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Content-Length') === 0) {
                return $headers;
            }
        }

        $headers['Content-Length'] = (string) $contentLength;

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    private function headersToString(array $headers): string
    {
        return implode("\r\n", $this->formatHeaders($headers));
    }

    /**
     * @param list<string> $headers
     */
    private function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function escapeHeaderValue(string $value): string
    {
        return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value);
    }

    private function assertHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new UploadException('Upload URL must be an absolute HTTP or HTTPS URL');
        }
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if ($port <= 0 || $port > 65535) {
                throw new UploadException('Upload URL port must be between 1 and 65535');
            }
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new UploadException('Unsupported upload URL scheme: ' . $scheme);
        }
    }

    /**
     * @param resource $ch
     */
    private function applyCurlProxy($ch): void
    {
        if ($this->proxy === null) {
            return;
        }

        curl_setopt($ch, CURLOPT_PROXY, $this->proxy->curlProxyUrl());
        curl_setopt($ch, CURLOPT_PROXYTYPE, $this->proxy->curlType());
        $userPassword = $this->proxy->curlUserPassword();
        if ($userPassword !== null) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $userPassword);
        }
    }
}
