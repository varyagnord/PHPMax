<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

interface HttpUploaderInterface
{
    public function uploadMultipart(
        string $url,
        string $fieldName,
        string $contents,
        string $filename,
        string $contentType
    ): HttpUploadResponse;

    /**
     * @param array<string, string> $headers
     * @param iterable<int, string> $chunks
     */
    public function uploadStream(
        string $url,
        array $headers,
        iterable $chunks,
        int $contentLength
    ): HttpUploadResponse;
}

