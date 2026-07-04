<?php

declare(strict_types=1);

namespace PHPMax\Domain\Auth;

use PHPMax\Support\Model;

class StartAuthResponse extends Model
{
    /** @var string|null */
    public $token;
    /** @var int|null */
    public $codeLength;
    /** @var int|null */
    public $requestMaxDuration;
    /** @var int|null */
    public $requestCountLeft;
    /** @var int|null */
    public $altActionDuration;

    protected static function schema(): array
    {
        return [
            'token' => ['type' => 'string', 'required' => true],
            'codeLength' => ['type' => 'int', 'required' => true],
            'requestMaxDuration' => ['type' => 'int', 'required' => true],
            'requestCountLeft' => ['type' => 'int', 'required' => true],
            'altActionDuration' => ['type' => 'int', 'required' => true],
        ];
    }
}

