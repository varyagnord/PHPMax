<?php

declare(strict_types=1);

namespace PHPMax\Files;

use PHPMax\Exception\UploadException;

abstract class BaseFile
{
    /** @var string|null */
    protected $raw;
    /** @var string|null */
    protected $path;
    /** @var string|null */
    protected $url;
    /** @var string|null */
    protected $name;

    public function __construct(?string $raw = null, ?string $path = null, ?string $url = null, ?string $name = null)
    {
        $sources = 0;
        foreach ([$raw, $path, $url] as $source) {
            if ($source !== null) {
                $sources++;
            }
        }

        if ($sources === 0) {
            throw new UploadException('Path, URL or raw data must be provided');
        }
        if ($sources > 1) {
            throw new UploadException('Only one of raw data, URL or path must be provided');
        }
        if ($raw !== null && ($name === null || $name === '')) {
            throw new UploadException('Name must be provided for raw data');
        }
        if ($url !== null) {
            $this->assertSupportedUrl($url);
        }

        $this->raw = $raw;
        $this->path = $path;
        $this->url = $url;
        $this->name = $name;
    }

    public static function fromPath(string $path, ?string $name = null)
    {
        return new static(null, $path, null, $name);
    }

    public static function fromUrl(string $url, ?string $name = null)
    {
        return new static(null, null, $url, $name);
    }

    public static function fromRaw(string $raw, string $name)
    {
        return new static($raw, null, null, $name);
    }

    public function read(): string
    {
        if ($this->raw !== null) {
            if ($this->raw === '') {
                throw new UploadException('Raw upload source is empty');
            }

            return $this->raw;
        }

        if ($this->path !== null) {
            if (!is_file($this->path) || !is_readable($this->path)) {
                throw new UploadException('File is not readable: ' . $this->path);
            }
            $contents = file_get_contents($this->path);
            if ($contents === false) {
                throw new UploadException('Failed to read file: ' . $this->path);
            }

            return $contents;
        }

        if ($this->url !== null) {
            $context = $this->urlContext('GET');
            $contents = @file_get_contents($this->url, false, $context);
            if ($contents === false) {
                throw new UploadException('Failed to read URL: ' . $this->urlForError());
            }
            $this->assertHttpStatusOk($http_response_header ?? [], 'Failed to read URL');

            return $contents;
        }

        throw new UploadException('Path, URL or raw data must be provided');
    }

    public function size(): int
    {
        if ($this->raw !== null) {
            if ($this->raw === '') {
                throw new UploadException('Raw upload source is empty');
            }

            return strlen($this->raw);
        }

        if ($this->path !== null) {
            if (!is_file($this->path)) {
                throw new UploadException('File does not exist: ' . $this->path);
            }
            $size = filesize($this->path);
            if ($size === false) {
                throw new UploadException('Failed to get file size: ' . $this->path);
            }

            return (int) $size;
        }

        if ($this->url !== null) {
            $headers = @get_headers($this->url, true, $this->urlContext('HEAD'));
            if (!is_array($headers)) {
                throw new UploadException('Failed to read URL headers: ' . $this->urlForError());
            }
            $this->assertHttpStatusOk($headers, 'Failed to read URL headers');

            $length = $this->headerValue($headers, 'Content-Length');
            if (is_array($length)) {
                $length = end($length);
            }
            if ($length === null || !is_numeric($length)) {
                throw new UploadException('URL response does not contain Content-Length: ' . $this->urlForError());
            }

            return (int) $length;
        }

        throw new UploadException('Path, URL or raw data must be provided');
    }

    /**
     * @return iterable<int, string>
     */
    public function iterChunks(int $size): iterable
    {
        if ($size <= 0) {
            throw new UploadException('Chunk size must be greater than zero');
        }

        if ($this->raw !== null) {
            $length = strlen($this->raw);
            for ($offset = 0; $offset < $length; $offset += $size) {
                yield substr($this->raw, $offset, $size);
            }
            return;
        }

        if ($this->path !== null) {
            if (!is_file($this->path) || !is_readable($this->path)) {
                throw new UploadException('File is not readable: ' . $this->path);
            }
            $handle = fopen($this->path, 'rb');
            if (!is_resource($handle)) {
                throw new UploadException('Failed to open file: ' . $this->path);
            }
            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, $size);
                    if ($chunk === false) {
                        throw new UploadException('Failed to read file chunk: ' . $this->path);
                    }
                    if ($chunk !== '') {
                        yield $chunk;
                    }
                }
            } finally {
                fclose($handle);
            }
            return;
        }

        if ($this->url !== null) {
            $context = $this->urlContext('GET');
            $handle = @fopen($this->url, 'rb', false, $context);
            if (!is_resource($handle)) {
                throw new UploadException('Failed to open URL: ' . $this->urlForError());
            }
            try {
                $meta = stream_get_meta_data($handle);
                $this->assertHttpStatusOk($meta['wrapper_data'] ?? [], 'Failed to open URL');

                while (!feof($handle)) {
                    $chunk = fread($handle, $size);
                    if ($chunk === false) {
                        throw new UploadException('Failed to read URL chunk: ' . $this->urlForError());
                    }
                    if ($chunk !== '') {
                        yield $chunk;
                    }
                }
            } finally {
                fclose($handle);
            }
        }
    }

    public function name(): string
    {
        return (string) $this->name;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    protected function inferName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }
        if ($this->path !== null) {
            return basename($this->path);
        }
        if ($this->url !== null) {
            $path = (string) parse_url($this->url, PHP_URL_PATH);
            return basename($path);
        }

        return '';
    }

    private function assertSupportedUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new UploadException('URL source must be an absolute HTTP or HTTPS URL');
        }
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if ($port <= 0 || $port > 65535) {
                throw new UploadException('URL source port must be between 1 and 65535');
            }
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new UploadException('Unsupported URL source scheme: ' . $scheme);
        }
    }

    /**
     * @return resource
     */
    private function urlContext(string $method)
    {
        return stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
    }

    /**
     * @param array<mixed> $headers
     */
    private function assertHttpStatusOk(array $headers, string $message): void
    {
        $status = $this->httpStatusCode($headers);
        if ($status === null) {
            throw new UploadException($message . ': missing HTTP status for ' . $this->urlForError());
        }
        if ($status < 200 || $status >= 300) {
            throw new UploadException($message . ': HTTP ' . $status . ' for ' . $this->urlForError());
        }
    }

    private function urlForError(): string
    {
        if ($this->url === null) {
            return '<none>';
        }

        $parts = parse_url($this->url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '<url>';
        }

        $url = strtolower((string) $parts['scheme']) . '://' . (string) $parts['host'];
        if (isset($parts['port'])) {
            $url .= ':' . (int) $parts['port'];
        }

        return $url . '/<redacted>';
    }

    /**
     * @param array<mixed> $headers
     */
    private function httpStatusCode(array $headers): ?int
    {
        $status = null;
        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $value, $matches)) {
                $status = (int) $matches[1];
                continue;
            }
            if (is_array($value)) {
                $nested = $this->httpStatusCode($value);
                if ($nested !== null) {
                    $status = $nested;
                }
            }
        }

        return $status;
    }

    /**
     * @param array<mixed> $headers
     * @return mixed
     */
    private function headerValue(array $headers, string $name)
    {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }
}
