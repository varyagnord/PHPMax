<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Support\Model;

class FolderList extends Model
{
    /** @var list<string> */
    public $foldersOrder = [];
    /** @var list<Folder> */
    public $folders = [];
    /** @var list<mixed> */
    public $allFilterExcludeFolders = [];
    /** @var int */
    public $folderSync = 0;

    protected static function schema(): array
    {
        return [
            'foldersOrder' => ['type' => 'list<string>', 'default' => static function (): array {
                return [];
            }],
            'folders' => ['type' => 'list<' . Folder::class . '>', 'default' => static function (): array {
                return [];
            }],
            'allFilterExcludeFolders' => ['type' => 'list<mixed>', 'default' => static function (): array {
                return [];
            }],
            'folderSync' => ['type' => 'int', 'default' => 0],
        ];
    }
}
