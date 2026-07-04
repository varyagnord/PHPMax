<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Exception\PHPMaxException;

class ConsoleSmsCodeProvider implements SmsCodeProviderInterface
{
    public function getCode(string $phone): string
    {
        if (PHP_SAPI !== 'cli') {
            throw new PHPMaxException('Console SMS provider can only be used in CLI');
        }
        fwrite(STDOUT, 'Enter SMS code for ' . $phone . ': ');
        $line = fgets(STDIN);
        if ($line === false) {
            throw new PHPMaxException('Failed to read SMS code from STDIN');
        }

        return trim($line);
    }
}

