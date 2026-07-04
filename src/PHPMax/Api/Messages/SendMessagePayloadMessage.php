<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class SendMessagePayloadMessage extends Model
{
    /** @var string|null */
    public $text;
    /** @var int|null */
    public $cid;
    /** @var array<int, mixed> */
    public $elements = [];
    /** @var array<int, mixed> */
    public $attaches = [];
    /** @var ReplyLink|null */
    public $link;

    protected static function schema(): array
    {
        return [
            'text' => ['type' => 'string', 'required' => true],
            'cid' => ['type' => 'int', 'required' => true],
            'elements' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'attaches' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'link' => ['type' => ReplyLink::class],
        ];
    }
}
