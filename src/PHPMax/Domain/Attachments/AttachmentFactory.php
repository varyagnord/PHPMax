<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

use PHPMax\Domain\AttachmentType;

final class AttachmentFactory
{
    private const MAP = [
        AttachmentType::PHOTO => PhotoAttachment::class,
        AttachmentType::VIDEO => VideoAttachment::class,
        AttachmentType::FILE => FileAttachment::class,
        AttachmentType::CONTACT => ContactAttachment::class,
        AttachmentType::STICKER => StickerAttachment::class,
        AttachmentType::AUDIO => AudioAttachment::class,
        AttachmentType::CONTROL => ControlAttachment::class,
        AttachmentType::INLINE_KEYBOARD => InlineKeyboardAttachment::class,
        AttachmentType::SHARE => ShareAttachment::class,
        AttachmentType::CALL => CallAttachment::class,
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return BaseAttachment
     */
    public static function fromArray(array $payload): BaseAttachment
    {
        $type = $payload['_type'] ?? ($payload['type'] ?? AttachmentType::UNKNOWN);
        $type = is_string($type) ? $type : AttachmentType::UNKNOWN;
        $class = self::MAP[$type] ?? UnknownAttachment::class;

        return $class::fromArray($payload);
    }
}
