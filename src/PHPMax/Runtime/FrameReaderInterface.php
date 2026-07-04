<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

use PHPMax\Transport\TransportInterface;

interface FrameReaderInterface
{
    public function read(TransportInterface $transport, float $timeout): string;
}
