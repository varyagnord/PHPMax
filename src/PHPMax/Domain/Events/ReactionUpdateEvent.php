<?php

declare(strict_types=1);

namespace PHPMax\Domain\Events;

use PHPMax\Domain\ReactionCounter;
use PHPMax\Support\Model;

class ReactionUpdateEvent extends Model
{
    /** @var string|null */
    public $messageId;
    /** @var int|null */
    public $chatId;
    /** @var list<ReactionCounter> */
    public $counters = [];
    /** @var int|null */
    public $totalCount;

    protected static function schema(): array
    {
        return [
            'messageId' => ['type' => 'string', 'required' => true],
            'chatId' => ['type' => 'int', 'required' => true],
            'counters' => ['type' => 'list<' . ReactionCounter::class . '>', 'default' => static function (): array {
                return [];
            }],
            'totalCount' => ['type' => 'int', 'required' => true],
        ];
    }
}
