<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Domain\Presence;
use PHPMax\Support\Model;

class PresenceEvent extends Model
{
    /** @var Presence|null */
    public $presence;
    /** @var int|null */
    public $userId;

    protected static function schema(): array
    {
        return [
            'presence' => ['type' => Presence::class, 'required' => true],
            'userId' => ['type' => 'int', 'required' => true],
        ];
    }
}
