<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use PHPMax\Exception\ProtocolException;

final class Lz4BlockCompression
{
    public function decompress(string $src, int $maxOutput = 5242880): string
    {
        $dst = '';
        $pos = 0;
        $len = strlen($src);

        while ($pos < $len) {
            $token = ord($src[$pos]);
            $pos++;

            $litLen = $token >> 4;
            if ($litLen === 15) {
                while ($pos < $len) {
                    $b = ord($src[$pos]);
                    $pos++;
                    $litLen += $b;
                    if ($b !== 255) {
                        break;
                    }
                }
            }

            if ($litLen > 0) {
                if ($pos + $litLen > $len) {
                    throw new ProtocolException('LZ4: literal length out of bounds');
                }
                $dst .= substr($src, $pos, $litLen);
                $pos += $litLen;
                if (strlen($dst) > $maxOutput) {
                    throw new ProtocolException('LZ4: output too large');
                }
            }

            if ($pos >= $len) {
                break;
            }

            if ($pos + 1 >= $len) {
                throw new ProtocolException('LZ4: incomplete offset');
            }

            $offset = ord($src[$pos]) | (ord($src[$pos + 1]) << 8);
            $pos += 2;
            if ($offset === 0) {
                throw new ProtocolException('LZ4: zero offset');
            }

            $matchLen = ($token & 0x0F) + 4;
            if (($token & 0x0F) === 0x0F) {
                while ($pos < $len) {
                    $b = ord($src[$pos]);
                    $pos++;
                    $matchLen += $b;
                    if ($b !== 255) {
                        break;
                    }
                }
            }

            $matchPos = strlen($dst) - $offset;
            if ($matchPos < 0) {
                throw new ProtocolException('LZ4: match out of bounds');
            }

            for ($i = 0; $i < $matchLen; $i++) {
                $dst .= $dst[$matchPos + ($i % $offset)];
            }

            if (strlen($dst) > $maxOutput) {
                throw new ProtocolException('LZ4: output too large');
            }
        }

        return $dst;
    }
}

