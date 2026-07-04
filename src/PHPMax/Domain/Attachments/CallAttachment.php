<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class CallAttachment extends BaseAttachment
{
    /** @var int|null */
    public $duration;
    /** @var int|string|null */
    public $conversationId;
    /** @var list<int> */
    public $contactIds = [];

    protected static function schema(): array
    {
        return parent::schema() + [
            'duration' => ['type' => 'int'],
            'conversationId' => ['type' => 'mixed'],
            'contactIds' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
