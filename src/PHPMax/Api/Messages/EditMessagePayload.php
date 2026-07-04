<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

use PHPMax\Support\Model;

class EditMessagePayload extends Model
{
    /** @var int|null */
    public $chatId;
    /** @var int|null */
    public $messageId;
    /** @var string|null */
    public $text;
    /** @var array<int, mixed> */
    public $elements = [];
    /** @var array<int, mixed> */
    public $attachments = [];

    protected static function schema(): array
    {
        return [
            'chatId' => ['type' => 'int', 'required' => true],
            'messageId' => ['type' => 'int', 'required' => true],
            'text' => ['type' => 'string', 'required' => true],
            'elements' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'attachments' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
