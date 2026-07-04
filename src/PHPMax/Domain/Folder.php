<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class Folder extends Model
{
    /** @var int */
    public $sourceId = 0;
    /** @var list<int> */
    public $include = [];
    /** @var list<mixed> */
    public $options = [];
    /** @var int */
    public $updateTime = 0;
    /** @var string */
    public $id = '';
    /** @var list<mixed> */
    public $filters = [];
    /** @var string */
    public $title = '';

    protected static function schema(): array
    {
        return [
            'sourceId' => ['type' => 'int', 'default' => 0],
            'include' => ['type' => 'list<int>', 'default' => static function (): array {
                return [];
            }],
            'options' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'updateTime' => ['type' => 'int', 'default' => 0],
            'id' => ['type' => 'string', 'default' => ''],
            'filters' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'title' => ['type' => 'string', 'default' => ''],
        ];
    }
}
