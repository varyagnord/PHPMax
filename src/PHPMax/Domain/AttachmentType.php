<?php

declare(strict_types=1);

namespace PHPMax\Domain;

final class AttachmentType
{
    public const PHOTO = 'PHOTO';
    public const VIDEO = 'VIDEO';
    public const FILE = 'FILE';
    public const CONTACT = 'CONTACT';
    public const STICKER = 'STICKER';
    public const AUDIO = 'AUDIO';
    public const CONTROL = 'CONTROL';
    public const INLINE_KEYBOARD = 'INLINE_KEYBOARD';
    public const SHARE = 'SHARE';
    public const CALL = 'CALL';
    public const UNKNOWN = 'UNKNOWN';

    private function __construct()
    {
    }
}

