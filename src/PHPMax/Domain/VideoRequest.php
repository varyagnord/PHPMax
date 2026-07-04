<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class VideoRequest extends Model
{
    /** @var mixed */
    public $external;
    /** @var bool|null */
    public $cache;
    /** @var string|null */
    public $url;

    protected static function schema(): array
    {
        return [
            'external' => ['type' => 'mixed', 'payload' => 'EXTERNAL'],
            'cache' => ['type' => 'bool', 'required' => true],
            'url' => ['type' => 'string', 'required' => true],
        ];
    }

    protected static function normalizeInput(array $data): array
    {
        if (isset($data['url'])) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ($key !== 'EXTERNAL' && $key !== 'cache') {
                $data['url'] = $value;
                break;
            }
        }

        return $data;
    }
}

