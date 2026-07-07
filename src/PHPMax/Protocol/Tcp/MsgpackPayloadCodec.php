<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use PHPMax\Exception\ProtocolException;

final class MsgpackPayloadCodec
{
    /**
     * @param mixed $payload
     */
    public function encode($payload): string
    {
        if ($payload === null) {
            return '';
        }
        if (function_exists('msgpack_pack') && !$this->containsBinaryString($payload)) {
            /** @var string $packed */
            $packed = msgpack_pack($payload);
            return $packed;
        }

        return $this->packValue($payload);
    }

    /**
     * @return mixed
     */
    public function decode(string $payloadBytes)
    {
        if ($payloadBytes === '') {
            return [];
        }
        if (function_exists('msgpack_unpack')) {
            return msgpack_unpack($payloadBytes);
        }

        $offset = 0;
        return $this->unpackValue($payloadBytes, $offset);
    }

    /**
     * @param mixed $value
     */
    private function packValue($value): string
    {
        if ($value === null) {
            return "\xC0";
        }
        if ($value === false) {
            return "\xC2";
        }
        if ($value === true) {
            return "\xC3";
        }
        if (is_int($value)) {
            return $this->packInt($value);
        }
        if (is_float($value)) {
            return "\xCB" . pack('E', $value);
        }
        if (is_string($value)) {
            return $this->packString($value);
        }
        if ($value instanceof BinaryString) {
            return $this->packBinary($value->bytes());
        }
        if (is_array($value)) {
            return $this->isList($value) ? $this->packArray($value) : $this->packMap($value);
        }

        throw new ProtocolException('Unsupported MessagePack value type: ' . gettype($value));
    }

    private function packInt(int $value): string
    {
        if ($value >= 0 && $value <= 0x7F) {
            return chr($value);
        }
        if ($value < 0 && $value >= -32) {
            return chr(0xE0 | ($value + 32));
        }
        if ($value >= 0 && $value <= 0xFF) {
            return "\xCC" . pack('C', $value);
        }
        if ($value >= 0 && $value <= 0xFFFF) {
            return "\xCD" . pack('n', $value);
        }
        if ($value >= 0 && $value <= 0xFFFFFFFF) {
            return "\xCE" . pack('N', $value);
        }
        if ($value >= 0) {
            return "\xCF" . $this->packUInt64($value);
        }
        if ($value >= -128 && $value < 0) {
            return "\xD0" . pack('c', $value);
        }
        if ($value >= -32768 && $value < 0) {
            return "\xD1" . pack('n', $value & 0xFFFF);
        }
        if ($value >= -2147483648 && $value < 0) {
            return "\xD2" . pack('N', $value & 0xFFFFFFFF);
        }

        return "\xD3" . $this->packInt64($value);
    }

    private function packString(string $value): string
    {
        $length = strlen($value);
        if ($length <= 31) {
            return chr(0xA0 | $length) . $value;
        }
        if ($length <= 0xFF) {
            return "\xD9" . pack('C', $length) . $value;
        }
        if ($length <= 0xFFFF) {
            return "\xDA" . pack('n', $length) . $value;
        }

        return "\xDB" . pack('N', $length) . $value;
    }

    private function packBinary(string $value): string
    {
        $length = strlen($value);
        if ($length <= 0xFF) {
            return "\xC4" . pack('C', $length) . $value;
        }
        if ($length <= 0xFFFF) {
            return "\xC5" . pack('n', $length) . $value;
        }

        return "\xC6" . pack('N', $length) . $value;
    }

    /**
     * @param array<int, mixed> $items
     */
    private function packArray(array $items): string
    {
        $length = count($items);
        if ($length <= 15) {
            $out = chr(0x90 | $length);
        } elseif ($length <= 0xFFFF) {
            $out = "\xDC" . pack('n', $length);
        } else {
            $out = "\xDD" . pack('N', $length);
        }
        foreach ($items as $item) {
            $out .= $this->packValue($item);
        }
        return $out;
    }

    /**
     * @param array<mixed> $items
     */
    private function packMap(array $items): string
    {
        $length = count($items);
        if ($length <= 15) {
            $out = chr(0x80 | $length);
        } elseif ($length <= 0xFFFF) {
            $out = "\xDE" . pack('n', $length);
        } else {
            $out = "\xDF" . pack('N', $length);
        }
        foreach ($items as $key => $value) {
            $out .= $this->packValue($key);
            $out .= $this->packValue($value);
        }
        return $out;
    }

    /**
     * @param mixed $value
     */
    private function containsBinaryString($value): bool
    {
        if ($value instanceof BinaryString) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if ($this->containsBinaryString($item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return mixed
     */
    private function unpackValue(string $bytes, int &$offset)
    {
        if ($offset >= strlen($bytes)) {
            throw new ProtocolException('MessagePack: unexpected end of payload');
        }

        $prefix = ord($bytes[$offset]);
        $offset++;

        if ($prefix <= 0x7F) {
            return $prefix;
        }
        if ($prefix >= 0xE0) {
            return $prefix - 0x100;
        }
        if (($prefix & 0xE0) === 0xA0) {
            return $this->readBytes($bytes, $offset, $prefix & 0x1F);
        }
        if (($prefix & 0xF0) === 0x90) {
            return $this->unpackArray($bytes, $offset, $prefix & 0x0F);
        }
        if (($prefix & 0xF0) === 0x80) {
            return $this->unpackMap($bytes, $offset, $prefix & 0x0F);
        }

        switch ($prefix) {
            case 0xC0:
                return null;
            case 0xC2:
                return false;
            case 0xC3:
                return true;
            case 0xCC:
                return $this->readUInt($bytes, $offset, 1);
            case 0xCD:
                return $this->readUInt($bytes, $offset, 2);
            case 0xCE:
                return $this->readUInt($bytes, $offset, 4);
            case 0xCF:
                return $this->readUInt64($bytes, $offset);
            case 0xD0:
                return $this->readInt($bytes, $offset, 1);
            case 0xD1:
                return $this->readInt($bytes, $offset, 2);
            case 0xD2:
                return $this->readInt($bytes, $offset, 4);
            case 0xD3:
                return $this->readInt64($bytes, $offset);
            case 0xD9:
                return $this->readBytes($bytes, $offset, $this->readUInt($bytes, $offset, 1));
            case 0xDA:
                return $this->readBytes($bytes, $offset, $this->readUInt($bytes, $offset, 2));
            case 0xDB:
                return $this->readBytes($bytes, $offset, $this->readUInt($bytes, $offset, 4));
            case 0xC4:
                return $this->readBytes($bytes, $offset, $this->readUInt($bytes, $offset, 1));
            case 0xC5:
                return $this->readBytes($bytes, $offset, $this->readUInt($bytes, $offset, 2));
            case 0xDC:
                return $this->unpackArray($bytes, $offset, $this->readUInt($bytes, $offset, 2));
            case 0xDD:
                return $this->unpackArray($bytes, $offset, $this->readUInt($bytes, $offset, 4));
            case 0xDE:
                return $this->unpackMap($bytes, $offset, $this->readUInt($bytes, $offset, 2));
            case 0xDF:
                return $this->unpackMap($bytes, $offset, $this->readUInt($bytes, $offset, 4));
            case 0xCA:
                $raw = $this->readBytes($bytes, $offset, 4);
                $unpacked = unpack('G', $raw);
                return $unpacked[1];
            case 0xCB:
                $raw = $this->readBytes($bytes, $offset, 8);
                $unpacked = unpack('E', $raw);
                return $unpacked[1];
        }

        throw new ProtocolException('Unsupported MessagePack prefix 0x' . strtoupper(dechex($prefix)));
    }

    /**
     * @return array<int, mixed>
     */
    private function unpackArray(string $bytes, int &$offset, int $length): array
    {
        $items = [];
        for ($i = 0; $i < $length; $i++) {
            $items[] = $this->unpackValue($bytes, $offset);
        }
        return $items;
    }

    /**
     * @return array<mixed>
     */
    private function unpackMap(string $bytes, int &$offset, int $length): array
    {
        $items = [];
        for ($i = 0; $i < $length; $i++) {
            $key = $this->unpackValue($bytes, $offset);
            $items[$key] = $this->unpackValue($bytes, $offset);
        }
        return $items;
    }

    private function readUInt(string $bytes, int &$offset, int $length): int
    {
        $raw = $this->readBytes($bytes, $offset, $length);
        if ($length === 1) {
            return ord($raw);
        }
        if ($length === 2) {
            $parts = unpack('n', $raw);
            return (int) $parts[1];
        }
        $parts = unpack('N', $raw);
        return (int) $parts[1];
    }

    private function readInt(string $bytes, int &$offset, int $length): int
    {
        $value = $this->readUInt($bytes, $offset, $length);
        $bits = $length * 8;
        $sign = 1 << ($bits - 1);
        if (($value & $sign) !== 0) {
            $value -= 1 << $bits;
        }
        return $value;
    }

    private function packUInt64(int $value): string
    {
        $high = intdiv($value, 4294967296);
        $low = $value % 4294967296;

        return pack('N2', $high, $low);
    }

    private function packInt64(int $value): string
    {
        if ($value >= 0) {
            return $this->packUInt64($value);
        }

        $absMinusOne = -($value + 1);
        $high = intdiv($absMinusOne, 4294967296);
        $low = $absMinusOne % 4294967296;

        return pack('N2', (~$high) & 0xFFFFFFFF, (~$low) & 0xFFFFFFFF);
    }

    private function readUInt64(string $bytes, int &$offset): int
    {
        $raw = $this->readBytes($bytes, $offset, 8);
        $parts = unpack('Nhigh/Nlow', $raw);
        $high = (int) $parts['high'];
        $low = (int) $parts['low'];

        if ($high > 0x7FFFFFFF) {
            throw new ProtocolException('MessagePack uint64 exceeds PHP integer range');
        }

        return $high * 4294967296 + $low;
    }

    private function readInt64(string $bytes, int &$offset): int
    {
        $raw = $this->readBytes($bytes, $offset, 8);
        $parts = unpack('Nhigh/Nlow', $raw);
        $high = (int) $parts['high'];
        $low = (int) $parts['low'];

        if (($high & 0x80000000) === 0) {
            return $high * 4294967296 + $low;
        }

        if ($high === 0x80000000 && $low === 0) {
            return PHP_INT_MIN;
        }

        $magnitude = ((~$high) & 0xFFFFFFFF) * 4294967296 + ((~$low) & 0xFFFFFFFF) + 1;

        return -$magnitude;
    }

    private function readBytes(string $bytes, int &$offset, int $length): string
    {
        if ($offset + $length > strlen($bytes)) {
            throw new ProtocolException('MessagePack: unexpected end of payload');
        }
        $raw = substr($bytes, $offset, $length);
        $offset += $length;
        return $raw;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        $index = 0;
        foreach ($value as $key => $_) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }
        return true;
    }
}
