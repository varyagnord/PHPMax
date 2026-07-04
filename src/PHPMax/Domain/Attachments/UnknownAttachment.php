<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

use PHPMax\Domain\AttachmentType;
use PHPMax\Exception\ValidationException;

class UnknownAttachment extends BaseAttachment
{
    private const KNOWN_TYPES = [
        AttachmentType::PHOTO,
        AttachmentType::VIDEO,
        AttachmentType::FILE,
        AttachmentType::STICKER,
        AttachmentType::AUDIO,
        AttachmentType::CONTROL,
        AttachmentType::CONTACT,
        AttachmentType::CALL,
        AttachmentType::SHARE,
        AttachmentType::INLINE_KEYBOARD,
    ];

    protected static function normalizeInput(array $data): array
    {
        $type = $data['_type'] ?? ($data['type'] ?? null);
        if (is_string($type) && in_array($type, self::KNOWN_TYPES, true)) {
            throw new ValidationException('Known attachment type should be parsed by its own model.');
        }

        return $data;
    }
}
