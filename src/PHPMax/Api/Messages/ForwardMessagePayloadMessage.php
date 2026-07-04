<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class ForwardMessagePayloadMessage extends Model
{
    /** @var int|null */
    public $cid;
    /** @var ForwardLink|null */
    public $link;
    /** @var array<int, mixed> */
    public $attaches = [];

    protected static function schema(): array
    {
        return [
            'cid' => ['type' => 'int', 'required' => true],
            'link' => ['type' => ForwardLink::class, 'required' => true],
            'attaches' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
