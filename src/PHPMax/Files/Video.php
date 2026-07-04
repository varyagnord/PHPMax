<?php

declare(strict_types=1);

namespace PHPMax\Files;

use PHPMax\Exception\UploadException;

class Video extends BaseFile
{
    public function __construct(?string $raw = null, ?string $path = null, ?string $url = null, ?string $name = null)
    {
        parent::__construct($raw, $path, $url, $name);
        $this->name = $this->inferName();
        if ($this->name === '') {
            throw new UploadException('Either name, URL or path must provide a video file name');
        }
    }
}

