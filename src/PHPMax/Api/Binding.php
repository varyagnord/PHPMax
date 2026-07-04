<?php

declare(strict_types=1);

namespace PHPMax\Api;

use PHPMax\Domain\Chat;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\Message;
use PHPMax\Domain\User;
use PHPMax\Runtime\App;
use PHPMax\Support\Model;

final class Binding
{
    private function __construct()
    {
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function bindApiModel(App $app, $value)
    {
        $seen = [];
        self::bindValue($app, $value, $seen);

        return $value;
    }

    /**
     * @param iterable<mixed> $values
     * @return list<mixed>
     */
    public static function bindApiModels(App $app, iterable $values): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[] = self::bindApiModel($app, $value);
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @param array<int, true> $seen
     */
    private static function bindValue(App $app, $value, array &$seen): void
    {
        if ($value === null || is_scalar($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::bindValue($app, $item, $seen);
            }
            return;
        }

        if (!is_object($value)) {
            return;
        }

        $id = spl_object_id($value);
        if (isset($seen[$id])) {
            return;
        }
        $seen[$id] = true;

        if ($value instanceof Message) {
            $value->bind($app->api()->messages);
        } elseif ($value instanceof Chat) {
            $value->bind($app->api()->messages, $app->api()->chats);
        } elseif ($value instanceof User) {
            $value->bind($app->api()->users);
        } elseif ($value instanceof MessageDeleteEvent) {
            $value->bind($app->api()->messages);
        }

        foreach (get_object_vars($value) as $item) {
            self::bindValue($app, $item, $seen);
        }

        if ($value instanceof Model) {
            foreach ($value->extra() as $item) {
                self::bindValue($app, $item, $seen);
            }
        }
    }
}
