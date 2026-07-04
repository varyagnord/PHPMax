<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use PHPMax\Exception\ProtocolException;

final class ZstdCompression
{
    public function decompress(string $src, int $maxOutput = 5242880): string
    {
        if (!function_exists('zstd_uncompress')) {
            throw new ProtocolException('Zstd-compressed TCP payload received, but ext-zstd is not available');
        }

        /** @var string|false $result */
        $result = zstd_uncompress($src);
        if ($result === false) {
            throw new ProtocolException('Zstd: failed to decompress payload');
        }
        if (strlen($result) > $maxOutput) {
            throw new ProtocolException('Zstd: output too large');
        }

        return $result;
    }
}

