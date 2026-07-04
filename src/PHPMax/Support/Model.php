<?php

declare(strict_types=1);

namespace PHPMax\Support;

use PHPMax\Exception\ValidationException;

abstract class Model
{
    /** @var array<string, mixed> */
    private $extra = [];

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data)
    {
        return new static($data);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function schema(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $excludeNull = true): array
    {
        $result = [];
        foreach (static::schema() as $property => $definition) {
            $key = isset($definition['payload']) ? (string) $definition['payload'] : (string) $property;
            $value = property_exists($this, $property) ? $this->{$property} : null;
            if ($excludeNull && $value === null) {
                continue;
            }
            $result[$key] = self::serializeValue($value, $excludeNull);
        }

        foreach ($this->extra as $key => $value) {
            if ($excludeNull && $value === null) {
                continue;
            }
            if (!array_key_exists($key, $result)) {
                $result[$key] = self::serializeValue($value, $excludeNull);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return $this->extra;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): void
    {
        $data = static::normalizeInput($data);
        $usedKeys = [];
        foreach (static::schema() as $property => $definition) {
            $found = false;
            $value = null;
            foreach ($this->candidateKeys((string) $property, $definition) as $key) {
                if (array_key_exists($key, $data)) {
                    $value = $data[$key];
                    $found = true;
                    $usedKeys[$key] = true;
                    break;
                }
            }

            if (!$found) {
                if (array_key_exists('default', $definition)) {
                    $this->{$property} = $this->resolveDefault($definition['default']);
                    continue;
                }
                if (!empty($definition['required'])) {
                    throw new ValidationException('Missing required field `' . $property . '` for ' . static::class);
                }
                $this->{$property} = null;
                continue;
            }

            $this->{$property} = $this->castValue($value, $definition);
        }

        if ($this->preservesExtraFields()) {
            foreach ($data as $key => $value) {
                if (!isset($usedKeys[$key])) {
                    $this->extra[(string) $key] = $value;
                }
            }
        }
    }

    private function preservesExtraFields(): bool
    {
        return strncmp(static::class, 'PHPMax\\Domain\\', strlen('PHPMax\\Domain\\')) === 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected static function normalizeInput(array $data): array
    {
        return $data;
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<string>
     */
    private function candidateKeys(string $property, array $definition): array
    {
        $keys = [$property, self::snakeToCamel($property), self::camelToSnake($property)];
        if (isset($definition['payload'])) {
            array_unshift($keys, (string) $definition['payload']);
        }
        if (isset($definition['aliases']) && is_array($definition['aliases'])) {
            foreach ($definition['aliases'] as $alias) {
                array_unshift($keys, (string) $alias);
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function resolveDefault($default)
    {
        return is_object($default) && is_callable($default) ? $default() : $default;
    }

    /**
     * @param array<string, mixed> $definition
     * @return mixed
     */
    private function castValue($value, array $definition)
    {
        if ($value === null) {
            return null;
        }

        if (isset($definition['factory']) && is_callable($definition['factory'])) {
            return $definition['factory']($value);
        }

        $type = isset($definition['type']) ? (string) $definition['type'] : 'mixed';

        if ($type === 'mixed') {
            return $value;
        }
        if ($type === 'int') {
            return self::castInt($value);
        }
        if ($type === 'string') {
            return self::castString($value);
        }
        if ($type === 'bool') {
            return self::castBool($value);
        }
        if ($type === 'array') {
            if (!is_array($value)) {
                throw new ValidationException('Expected array value for ' . static::class);
            }
            return $value;
        }
        if (strpos($type, 'list<') === 0 && substr($type, -1) === '>') {
            $class = substr($type, 5, -1);
            if (!is_array($value) || !self::isListArray($value)) {
                throw new ValidationException('Expected list value for ' . static::class);
            }
            $items = [];
            foreach ($value as $item) {
                $items[] = self::castListItem($class, $item);
            }
            return $items;
        }
        if (strpos($type, 'map-list<') === 0 && substr($type, -1) === '>') {
            $class = substr($type, 9, -1);
            if (!is_array($value)) {
                throw new ValidationException('Expected map-list value for ' . static::class);
            }
            $map = [];
            foreach ($value as $key => $items) {
                if (!is_array($items) || !self::isListArray($items)) {
                    throw new ValidationException('Expected list items for map-list field in ' . static::class);
                }
                $map[$key] = [];
                foreach ($items as $item) {
                    $map[$key][] = self::castListItem($class, $item);
                }
            }
            return $map;
        }

        if (is_a($type, self::class, true)) {
            return $this->castClass($type, $value);
        }

        return $value;
    }

    /**
     * @return mixed
     */
    private function castClass(string $class, $value)
    {
        if ($value instanceof $class) {
            return $value;
        }
        if (!is_array($value)) {
            throw new ValidationException('Expected array for model `' . $class . '`');
        }
        return $class::fromArray($value);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function castListItem(string $type, $value)
    {
        if ($type === 'mixed') {
            return $value;
        }
        if ($type === 'int') {
            return self::castInt($value);
        }
        if ($type === 'string') {
            return self::castString($value);
        }
        if ($type === 'bool') {
            return self::castBool($value);
        }
        if ($type === 'array') {
            if (!is_array($value)) {
                throw new ValidationException('Expected array list item');
            }

            return $value;
        }
        if (is_a($type, self::class, true)) {
            if ($value instanceof $type) {
                return $value;
            }
            if (!is_array($value)) {
                throw new ValidationException('Expected array for model `' . $type . '`');
            }

            return $type::fromArray($value);
        }

        throw new ValidationException('Unsupported list item type `' . $type . '`');
    }

    /**
     * @return mixed
     */
    private static function serializeValue($value, bool $excludeNull)
    {
        if ($value instanceof self) {
            return $value->toArray($excludeNull);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if ($excludeNull && $item === null) {
                    continue;
                }
                $result[$key] = self::serializeValue($item, $excludeNull);
            }
            return $result;
        }
        return $value;
    }

    private static function snakeToCamel(string $value): string
    {
        return preg_replace_callback('/_([a-z])/', static function (array $matches): string {
            return strtoupper($matches[1]);
        }, $value) ?? $value;
    }

    private static function camelToSnake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value);
    }

    /**
     * @param array<mixed> $value
     */
    protected static function isListArray(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    private static function castInt($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            if (floor($value) !== $value) {
                throw new ValidationException('Expected integer-compatible value');
            }

            return (int) $value;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^[+-]?\d+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
            if (preg_match('/^[+-]?\d+\.0+$/', $trimmed) === 1) {
                return (int) $trimmed;
            }
        }

        throw new ValidationException('Expected integer-compatible value');
    }

    /**
     * @param mixed $value
     */
    private static function castString($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        throw new ValidationException('Expected string value');
    }

    /**
     * @param mixed $value
     */
    private static function castBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
                return false;
            }
        }

        throw new ValidationException('Expected boolean-compatible value');
    }
}
