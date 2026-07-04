<?php

declare(strict_types=1);

namespace PHPMax\Exception;

class ApiException extends PHPMaxException
{
    /** @var int */
    private $opcode;
    /** @var string|null */
    private $error;
    /** @var string|null */
    private $title;
    /** @var string|null */
    private $apiMessage;
    /** @var string|null */
    private $localizedMessage;
    /** @var array<string, mixed> */
    private $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        int $opcode,
        ?string $error = null,
        ?string $title = null,
        ?string $apiMessage = null,
        ?string $localizedMessage = null,
        array $payload = []
    ) {
        $this->opcode = $opcode;
        $this->error = $error;
        $this->title = $title;
        $this->apiMessage = $apiMessage;
        $this->localizedMessage = $localizedMessage;
        $this->payload = $payload;

        parent::__construct($this->buildMessage());
    }

    public function opcode(): int
    {
        return $this->opcode;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function apiMessage(): ?string
    {
        return $this->apiMessage;
    }

    public function localizedMessage(): ?string
    {
        return $this->localizedMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    private function buildMessage(): string
    {
        $parts = [];
        foreach ([$this->localizedMessage, $this->apiMessage] as $part) {
            if ($part !== null && $part !== '' && !in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }
        if ($this->title !== null && $this->title !== '') {
            $parts[] = '(' . $this->title . ')';
        }
        if ($this->error !== null && $this->error !== '') {
            $parts[] = '[' . $this->error . ']';
        }

        return $parts !== [] ? implode(' ', $parts) : 'API request failed';
    }
}
