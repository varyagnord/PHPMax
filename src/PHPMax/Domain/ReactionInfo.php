<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class ReactionInfo extends Model
{
    /** @var int|null */
    public $totalCount;
    /** @var list<ReactionCounter> */
    public $counters = [];
    /** @var string|null */
    public $yourReaction;

    protected static function schema(): array
    {
        return [
            'totalCount' => ['type' => 'int', 'default' => 0],
            'counters' => ['type' => 'list<' . ReactionCounter::class . '>', 'default' => static function (): array {
                return [];
            }],
            'yourReaction' => ['type' => 'string'],
        ];
    }
}

