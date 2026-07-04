<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class CreateFolderPayload extends Model
{
    /** @var string|null */
    public $id;
    /** @var string|null */
    public $title;
    /** @var list<int> */
    public $include = [];
    /** @var list<mixed> */
    public $filters = [];

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => true],
            'include' => ['type' => 'list<int>', 'required' => true],
            'filters' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
