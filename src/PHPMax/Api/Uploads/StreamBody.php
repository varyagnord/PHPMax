<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use ArrayIterator;
use Iterator;
use IteratorAggregate;
use PHPMax\Exception\UploadException;
use Traversable;

class StreamBody
{
    /** @var Iterator */
    private $chunks;
    /** @var string */
    private $buffer = '';
    /** @var int */
    private $expectedLength;
    /** @var int */
    private $bytesRead = 0;
    /** @var bool */
    private $exhausted = false;

    public function __construct(iterable $chunks, int $expectedLength)
    {
        if ($expectedLength < 0) {
            throw new UploadException('Expected stream length must not be negative');
        }

        $this->chunks = self::toIterator($chunks);
        $this->chunks->rewind();
        $this->expectedLength = $expectedLength;
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        while (strlen($this->buffer) < $length && !$this->exhausted) {
            $this->appendNextChunk();
        }

        if ($this->buffer === '') {
            return '';
        }

        $data = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($data));
        $this->bytesRead += strlen($data);

        if ($this->bytesRead > $this->expectedLength) {
            throw new UploadException('Upload stream exceeded expected size: expected ' . $this->expectedLength . ', got at least ' . $this->bytesRead);
        }

        return $data;
    }

    public function assertComplete(): void
    {
        if ($this->bytesRead !== $this->expectedLength) {
            throw new UploadException('Upload stream size mismatch: expected ' . $this->expectedLength . ', got ' . $this->bytesRead);
        }
    }

    public function bytesRead(): int
    {
        return $this->bytesRead;
    }

    private function appendNextChunk(): void
    {
        while ($this->chunks->valid()) {
            $chunk = $this->chunks->current();
            $this->chunks->next();

            if (!is_string($chunk)) {
                throw new UploadException('Upload stream chunk must be a string');
            }
            if ($chunk === '') {
                continue;
            }

            $this->buffer .= $chunk;
            return;
        }

        $this->exhausted = true;
    }

    private static function toIterator(iterable $chunks): Iterator
    {
        if (is_array($chunks)) {
            return new ArrayIterator($chunks);
        }
        if ($chunks instanceof Iterator) {
            return $chunks;
        }
        if ($chunks instanceof IteratorAggregate) {
            $iterator = $chunks->getIterator();
            if ($iterator instanceof Iterator) {
                return $iterator;
            }
            if ($iterator instanceof Traversable) {
                return self::toIterator(iterator_to_array($iterator, false));
            }
        }

        throw new UploadException('Upload stream chunks must be iterable');
    }
}

