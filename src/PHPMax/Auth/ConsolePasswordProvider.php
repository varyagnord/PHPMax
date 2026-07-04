<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Exception\PHPMaxException;

class ConsolePasswordProvider implements PasswordProviderInterface
{
    public function getPassword(?string $hint = null): string
    {
        if (PHP_SAPI !== 'cli') {
            throw new PHPMaxException('Console password provider can only be used in CLI');
        }
        $prompt = 'Enter 2FA password';
        if ($hint !== null && $hint !== '') {
            $prompt .= ' (hint: ' . $hint . ')';
        }
        fwrite(STDOUT, $prompt . ': ');
        $line = fgets(STDIN);
        if ($line === false) {
            throw new PHPMaxException('Failed to read 2FA password from STDIN');
        }

        return trim($line);
    }
}

