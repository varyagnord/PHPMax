<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Exception\PHPMaxException;

class ConsoleQrHandler implements QrHandlerInterface
{
    public function showQr(string $qrUrl): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new PHPMaxException('Console QR handler can only be used in CLI');
        }

        fwrite(STDOUT, 'Open or scan QR login URL: ' . $qrUrl . PHP_EOL);
    }
}
