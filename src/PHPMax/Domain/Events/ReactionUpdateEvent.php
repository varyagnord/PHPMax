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

    protected static function normalizeInput(array $data): array
    {
        // MAX может прислать messageId реакции числом. Для EasyChat это
        // служебное событие не должно ронять весь короткий polling-цикл.
        if (array_key_exists('messageId', $data) && (is_int($data['messageId']) || is_float($data['messageId']))) {
            $data['messageId'] = (string)$data['messageId'];
        }
        if (array_key_exists('message_id', $data) && (is_int($data['message_id']) || is_float($data['message_id']))) {
            $data['message_id'] = (string)$data['message_id'];
        }

        return $data;
    }
}
