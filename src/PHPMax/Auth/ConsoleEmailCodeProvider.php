<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Exception\PHPMaxException;

class ConsoleEmailCodeProvider implements EmailCodeProviderInterface
{
    public function getCode(string $email): string
    {
        if (PHP_SAPI !== 'cli') {
            throw new PHPMaxException('Console email code provider can only be used in CLI');
        }
        fwrite(STDOUT, 'Enter 2FA email code for ' . $email . ': ');
        $line = fgets(STDIN);
        if ($line === false) {
            throw new PHPMaxException('Failed to read 2FA email code from STDIN');
        }

        return trim($line);
    }
}
