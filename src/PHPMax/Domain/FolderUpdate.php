<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class FolderUpdate extends Model
{
    /** @var list<string> */
    public $foldersOrder = [];
    /** @var Folder|null */
    public $folder;
    /** @var int */
    public $folderSync = 0;

    protected static function schema(): array
    {
        return [
            'foldersOrder' => ['type' => 'list<string>', 'default' => static function (): array {
                return [];
            }],
            'folder' => ['type' => Folder::class],
            'folderSync' => ['type' => 'int', 'default' => 0],
        ];
    }
}
