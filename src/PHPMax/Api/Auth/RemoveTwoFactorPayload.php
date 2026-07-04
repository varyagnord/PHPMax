<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class RemoveTwoFactorPayload extends Model
{
    /** @var string|null */
    public $trackId;
    /** @var bool|null */
    public $remove2fa;
    /** @var list<int> */
    public $expectedCapabilities = [];

    protected static function schema(): array
    {
        return [
            'trackId' => ['type' => 'string', 'required' => true],
            'remove2fa' => ['type' => 'bool', 'default' => true],
            'expectedCapabilities' => ['type' => 'list<int>', 'default' => static function (): array {
                return [TwoFactorAction::REMOVE_2FA];
            }],
        ];
    }
}
