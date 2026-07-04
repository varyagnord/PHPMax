<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

final class TcpPacketFramer
{
    public const HEADER_SIZE = 10;

    public function pack(int $ver, int $cmd, int $seq, int $opcode, int $flags, string $payloadBytes): string
    {
        $payloadLen = strlen($payloadBytes);
        $packedLen = (($flags & 0xFF) << 24) | ($payloadLen & 0x00FFFFFF);

        return pack('CCnnN', $ver, $cmd, $seq, $opcode, $packedLen) . $payloadBytes;
    }

    public function unpack(string $data): ?PackedPacket
    {
        if (strlen($data) < self::HEADER_SIZE) {
            return null;
        }

        $parts = unpack('Cver/Ccmd/nseq/nopcode/NpackedLen', substr($data, 0, self::HEADER_SIZE));
        if ($parts === false) {
            return null;
        }

        $flags = ((int) $parts['packedLen'] >> 24) & 0xFF;
        $payloadLen = (int) $parts['packedLen'] & 0x00FFFFFF;
        $totalLen = self::HEADER_SIZE + $payloadLen;
        if (strlen($data) < $totalLen) {
            return null;
        }

        return new PackedPacket(
            new TcpPacketHeader(
                (int) $parts['ver'],
                (int) $parts['cmd'],
                (int) $parts['seq'],
                (int) $parts['opcode'],
                $flags,
                $payloadLen
            ),
            substr($data, self::HEADER_SIZE, $payloadLen)
        );
    }

    public function unpackHeader(string $data): ?int
    {
        if (strlen($data) < self::HEADER_SIZE) {
            return null;
        }

        $parts = unpack('Cver/Ccmd/nseq/nopcode/NpackedLen', substr($data, 0, self::HEADER_SIZE));
        if ($parts === false) {
            return null;
        }

        return (int) $parts['packedLen'] & 0x00FFFFFF;
    }
}

