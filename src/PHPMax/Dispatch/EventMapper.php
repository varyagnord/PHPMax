<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

use PHPMax\Domain\Chat;
use PHPMax\Domain\Events\FileUploadSignal;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\Events\MessageReadEvent;
use PHPMax\Domain\Events\PresenceEvent;
use PHPMax\Domain\Events\ReactionUpdateEvent;
use PHPMax\Domain\Events\TypingEvent;
use PHPMax\Domain\Events\VideoUploadSignal;
use PHPMax\Domain\Message;
use PHPMax\Exception\ValidationException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;

class EventMapper
{
    /**
     * @return mixed
     */
    public function map(string $eventType, InboundFrame $frame)
    {
        if ($frame->cmd !== Command::REQUEST || $frame->payload === null || $frame->payload === []) {
            return $frame;
        }

        if ($eventType === EventType::MESSAGE_NEW || $eventType === EventType::MESSAGE_EDIT) {
            return Message::fromArray($frame->payload);
        }
        if ($eventType === EventType::CHAT_UPDATE) {
            if (
                !array_key_exists('chat', $frame->payload)
                || !is_array($frame->payload['chat'])
                || $frame->payload['chat'] === []
            ) {
                throw new ValidationException('Invalid chat update event payload: missing `chat` object');
            }
            return Chat::fromArray($frame->payload['chat']);
        }
        if ($eventType === EventType::MESSAGE_DELETE) {
            return MessageDeleteEvent::fromArray($frame->payload);
        }
        if ($eventType === EventType::MESSAGE_READ) {
            return MessageReadEvent::fromArray($frame->payload);
        }
        if ($eventType === EventType::TYPING) {
            return TypingEvent::fromArray($frame->payload);
        }
        if ($eventType === EventType::PRESENCE) {
            return PresenceEvent::fromArray($frame->payload);
        }
        if ($eventType === EventType::REACTION_UPDATE) {
            return ReactionUpdateEvent::fromArray($frame->payload);
        }
        if ($eventType === EventType::VIDEO_READY) {
            return VideoUploadSignal::fromArray($frame->payload);
        }
        if ($eventType === EventType::FILE_READY) {
            return FileUploadSignal::fromArray($frame->payload);
        }

        return $frame;
    }
}
