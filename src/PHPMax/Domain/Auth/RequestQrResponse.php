<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class RequestQrResponse extends Model
{
    /** @var int|null */
    public $expiresAt;
    /** @var int|null */
    public $pollingInterval;
    /** @var string|null */
    public $qrLink;
    /** @var string|null */
    public $trackId;
    /** @var int|null */
    public $ttl;

    protected static function schema(): array
    {
        return [
            'expiresAt' => ['type' => 'int', 'required' => true],
            'pollingInterval' => ['type' => 'int', 'required' => true],
            'qrLink' => ['type' => 'string', 'required' => true],
            'trackId' => ['type' => 'string', 'required' => true],
            'ttl' => ['type' => 'int', 'required' => true],
        ];
    }
}
