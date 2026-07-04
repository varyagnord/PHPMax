<?php

declare(strict_types=1);

namespace PHPMax\Domain;

final class TranscriptionStatus
{
    public const FAILED = 'FAILED';
    public const MEDIA_NOT_READY = 'MEDIA_NOT_READY';
    public const NOT_SUPPORTED = 'NOT_SUPPORTED';
    public const PROCESSING = 'PROCESSING';
    public const SUCCESS = 'SUCCESS';
    public const UNKNOWN = 'UNKNOWN';

    private function __construct()
    {
    }
}
