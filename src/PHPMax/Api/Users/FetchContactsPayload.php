<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Support\Model;

class FetchContactsPayload extends Model
{
    /** @var list<int> */
    public $contactIds = [];

    protected static function schema(): array
    {
        return [
            'contactIds' => ['type' => 'list<int>', 'required' => true],
        ];
    }
}
