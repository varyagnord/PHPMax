<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

final class BinaryString
{
    /** @var string */
    private $bytes;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }
}
