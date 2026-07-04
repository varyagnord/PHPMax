<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class AudioAttachment extends BaseAttachment
{
    /** @var int|null */
    public $duration;
    /** @var int|null */
    public $audioId;
    /** @var string|null */
    public $wave;
    /** @var string|null */
    public $transcriptionStatus;
    /** @var string|null */
    public $url;
    /** @var string|null */
    public $token;

    protected static function schema(): array
    {
        return parent::schema() + [
            'duration' => ['type' => 'int'],
            'audioId' => ['type' => 'int'],
            'wave' => ['type' => 'string'],
            'transcriptionStatus' => ['type' => 'string'],
            'url' => ['type' => 'string'],
            'token' => ['type' => 'string'],
        ];
    }
}
