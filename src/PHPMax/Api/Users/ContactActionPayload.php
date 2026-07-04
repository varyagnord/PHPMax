<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Support\Model;

class ContactActionPayload extends Model
{
    /** @var int|null */
    public $contactId;
    /** @var string|null */
    public $action;

    protected static function schema(): array
    {
        return [
            'contactId' => ['type' => 'int', 'required' => true],
            'action' => ['type' => 'string', 'required' => true],
        ];
    }
}
