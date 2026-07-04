<?php

declare(strict_types=1);

namespace PHPMax\Protocol;

class OutboundFrame
{
    /** @var int */
    public $ver;
    /** @var int */
    public $opcode;
    /** @var int */
    public $cmd;
    /** @var int */
    public $seq;
    /** @var array<mixed>|null */
    public $payload;

    /**
     * @param array<mixed>|null $payload
     */
    public function __construct(int $ver, int $opcode, int $seq, ?array $payload = null, int $cmd = Command::REQUEST)
    {
        $this->ver = $ver;
        $this->opcode = $opcode;
        $this->cmd = $cmd;
        $this->seq = $seq;
        $this->payload = $payload;
    }
}

