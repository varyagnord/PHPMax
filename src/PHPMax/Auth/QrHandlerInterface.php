<?php

declare(strict_types=1);

namespace PHPMax\Auth;

interface QrHandlerInterface
{
    public function showQr(string $qrUrl): void;
}
