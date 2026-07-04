<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Support\Model;

class UpdateFolderPayload extends Model
{
    /** @var string|null */
    public $id;
    /** @var string|null */
    public $title;
    /** @var list<int> */
    public $include = [];
    /** @var list<mixed> */
    public $filters = [];
    /** @var list<mixed> */
    public $options = [];

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'string', 'required' => true],
            'title' => ['type' => 'string', 'required' => true],
            'include' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
            'filters' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'options' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
        ];
    }
}
