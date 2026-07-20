<?php

declare(strict_types=1);

namespace PHPMax\Protocol\Tcp;

use InvalidArgumentException;

/**
 * Неизвестный MessagePack extension сохраняется без потери типа и данных.
 *
 * MAX может добавлять новые extension-типы независимо от версии PHPMax.
 * Протокольный слой не должен превращать их в null или строку: вызывающий код
 * сможет явно принять решение, когда контракт нового типа станет известен.
 */
final class MessagePackExtension
{
    /** @var int */
    private $type;

    /** @var string */
    private $data;

    public function __construct(int $type, string $data)
    {
        if ($type < -128 || $type > 127) {
            throw new InvalidArgumentException('MessagePack extension type must fit signed int8');
        }

        $this->type = $type;
        $this->data = $data;
    }

    public function type(): int
    {
        return $this->type;
    }

    public function data(): string
    {
        return $this->data;
    }
}
