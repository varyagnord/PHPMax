<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class ApproveQrLoginPayload extends Model
{
    /** @var string|null */
    public $qrLink;

    protected static function schema(): array
    {
        return [
            'qrLink' => ['type' => 'string', 'required' => true],
        ];
    }
}
