<?php

declare(strict_types=1);

namespace PHPMax\Transport;

interface TransportInterface
{
    public function connect(): void;

    public function close(): void;

    public function send(string $data): void;

    public function recv(int $length, float $timeout): string;

    public function connected(): bool;
}

