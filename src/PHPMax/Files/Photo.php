<?php

declare(strict_types=1);

namespace PHPMax\Files;

use PHPMax\Exception\UploadException;

class Photo extends BaseFile
{
    /** @var array<string, string> */
    private static $mimeByExtension = [
        '.jpg' => 'image/jpeg',
        '.jpeg' => 'image/jpeg',
        '.png' => 'image/png',
        '.gif' => 'image/gif',
        '.webp' => 'image/webp',
        '.bmp' => 'image/bmp',
    ];

    /** @var string */
    private $fileName = '';

    public function __construct(?string $raw = null, ?string $path = null, ?string $url = null, ?string $name = null)
    {
        parent::__construct($raw, $path, $url, $name);
        $this->fileName = $this->inferName();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function validatePhoto(): array
    {
        if ($this->url !== null) {
            $sourceName = (string) parse_url($this->url, PHP_URL_PATH);
            $extension = strtolower((string) strrchr($sourceName, '.'));
            if ($extension === '' || !isset(self::$mimeByExtension[$extension])) {
                throw new UploadException('Invalid photo extension: ' . ($extension !== '' ? $extension : '<none>'));
            }

            return [substr($extension, 1), self::$mimeByExtension[$extension]];
        }

        $sourceName = $this->path !== null ? $this->path : $this->fileName;
        $extension = strtolower((string) strrchr($sourceName, '.'));
        if ($extension === '' || !isset(self::$mimeByExtension[$extension])) {
            throw new UploadException('Invalid photo extension: ' . ($extension !== '' ? $extension : '<none>'));
        }

        return [substr($extension, 1), 'image/' . substr($extension, 1)];
    }

    public function fileName(): string
    {
        return $this->fileName;
    }
}
