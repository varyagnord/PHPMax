<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Protocol\Tcp\BinaryString;
use PHPMax\Support\Model;

class Exp extends Model
{
    /** @var BinaryString|null */
    public $chatsCountGroups;

    protected static function schema(): array
    {
        return [
            'chatsCountGroups' => ['type' => 'mixed', 'default' => static function (): BinaryString {
                return new BinaryString("\x0a\x32");
            }],
        ];
    }
}
