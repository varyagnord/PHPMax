<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use PHPMax\Exception\ProtocolException;

final class TcpPayloadDecoder
{
    /** @var MsgpackPayloadCodec */
    private $serializer;
    /** @var Lz4BlockCompression */
    private $compression;
    /** @var ZstdCompression */
    private $zstdCompression;

    public function __construct(
        ?MsgpackPayloadCodec $serializer = null,
        ?Lz4BlockCompression $compression = null,
        ?ZstdCompression $zstdCompression = null
    ) {
        $this->serializer = $serializer ?: new MsgpackPayloadCodec();
        $this->compression = $compression ?: new Lz4BlockCompression();
        $this->zstdCompression = $zstdCompression ?: new ZstdCompression();
    }

    /**
     * @return array<mixed>
     */
    public function decode(string $payloadBytes, int $flags = 0): array
    {
        if ($payloadBytes === '') {
            return [];
        }

        if ($flags === 0xFF) {
            $payloadBytes = $this->zstdCompression->decompress($payloadBytes);
        } elseif ($flags > 0x7F) {
            throw new ProtocolException('invalid TCP compression factor: ' . $flags);
        } elseif ($flags > 0) {
            $payloadBytes = $this->compression->decompress($payloadBytes);
        }

        $result = $this->serializer->decode($payloadBytes);
        if (!is_array($result)) {
            return [];
        }

        return $this->normalizeKeys($result);
    }

    /**
     * @param mixed $obj
     * @return mixed
     */
    private function normalizeKeys($obj)
    {
        if (!is_array($obj)) {
            return $obj;
        }
        $result = [];
        foreach ($obj as $key => $value) {
            $result[$this->normalizeKey($key)] = $this->normalizeKeys($value);
        }
        return $result;
    }

    /**
     * @param mixed $key
     */
    private function normalizeKey($key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }
        if (is_string($key)) {
            return $key;
        }
        return (string) $key;
    }
}

