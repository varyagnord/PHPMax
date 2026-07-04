<?php

declare(strict_types=1);

namespace PHPMax\Protocol;

class InboundFrame
{
    /** @var int */
    public $opcode;
    /** @var int */
    public $cmd;
    /** @var int|null */
    public $seq;
    /** @var array<mixed>|null */
    public $payload;
    /** @var array<mixed>|null */
    public $raw;

    /**
     * @param array<mixed>|null $payload
     * @param array<mixed>|null $raw
     */
    public function __construct(int $opcode, int $cmd = 0, ?int $seq = null, ?array $payload = null, ?array $raw = null)
    {
        $this->opcode = $opcode;
        $this->cmd = $cmd;
        $this->seq = $seq;
        $this->payload = $payload;
        $this->raw = $raw;
    }
}

