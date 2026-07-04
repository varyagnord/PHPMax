<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Support\Model;

class SetTwoFactorPayload extends Model
{
    /** @var list<int> */
    public $expectedCapabilities = [];
    /** @var string|null */
    public $trackId;
    /** @var string|null */
    public $password;
    /** @var string|null */
    public $hint;

    protected static function schema(): array
    {
        return [
            'expectedCapabilities' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
            'trackId' => ['type' => 'string', 'required' => true],
            'password' => ['type' => 'string', 'required' => true],
            'hint' => ['type' => 'string'],
        ];
    }
}
