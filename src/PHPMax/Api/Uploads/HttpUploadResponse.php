<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Exception\UploadException;

class HttpUploadResponse
{
    /** @var int */
    private $status;
    /** @var string */
    private $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        if (!is_array($decoded)) {
            throw new UploadException('Failed to decode upload response JSON');
        }

        return $decoded;
    }
}

