<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

class TcpPacketHeader
{
    /** @var int */
    public $ver;
    /** @var int */
    public $cmd;
    /** @var int */
    public $seq;
    /** @var int */
    public $opcode;
    /** @var int */
    public $flags;
    /** @var int */
    public $payloadLen;

    public function __construct(int $ver, int $cmd, int $seq, int $opcode, int $flags = 0, int $payloadLen = 0)
    {
        $this->ver = $ver;
        $this->cmd = $cmd;
        $this->seq = $seq;
        $this->opcode = $opcode;
        $this->flags = $flags;
        $this->payloadLen = $payloadLen;
    }
}

