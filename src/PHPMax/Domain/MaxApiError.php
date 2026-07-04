<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class MaxApiError extends Model
{
    /** @var string|null */
    public $error;
    /** @var string|null */
    public $title;
    /** @var string|null */
    public $message;
    /** @var string|null */
    public $localizedMessage;

    protected static function schema(): array
    {
        return [
            'error' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'localizedMessage' => ['type' => 'string'],
        ];
    }
}

