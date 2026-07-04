<?php

declare(strict_types=1);

namespace PHPMax\Protocol;

interface FrameProtocolInterface
{
    public function version(): int;

    public function encode(OutboundFrame $frame): string;

    public function decode(string $raw): InboundFrame;
}
