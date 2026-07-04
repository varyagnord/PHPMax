<?php

declare(strict_types=1);

namespace PHPMax\Transport;

interface MessageTransportInterface
{
    public function recvMessage(float $timeout): string;
}
