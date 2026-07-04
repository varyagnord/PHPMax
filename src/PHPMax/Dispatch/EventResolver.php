<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

use PHPMax\Domain\Events\FileUploadSignal;
use PHPMax\Domain\Events\VideoUploadSignal;
use PHPMax\Domain\Message;
use PHPMax\Domain\MessageStatus;
use PHPMax\Exception\ValidationException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;

class EventResolver
{
    public function resolve(InboundFrame $frame): ?string
    {
        if ($frame->cmd !== Command::REQUEST) {
            return null;
        }

        switch ($frame->opcode) {
            case Opcode::NOTIF_MESSAGE:
            case Opcode::MSG_EDIT:
                return $this->resolveMessage($frame);
            case Opcode::NOTIF_CHAT:
                return EventType::CHAT_UPDATE;
            case Opcode::NOTIF_MSG_DELETE:
                return EventType::MESSAGE_DELETE;
            case Opcode::NOTIF_ATTACH:
                return $this->resolveAttach($frame);
            case Opcode::NOTIF_TYPING:
                return EventType::TYPING;
            case Opcode::NOTIF_MARK:
                return EventType::MESSAGE_READ;
            case Opcode::NOTIF_PRESENCE:
                return EventType::PRESENCE;
            case Opcode::NOTIF_MSG_REACTIONS_CHANGED:
                return EventType::REACTION_UPDATE;
        }

        return null;
    }

    private function resolveMessage(InboundFrame $frame): ?string
    {
        if ($frame->payload === null) {
            return null;
        }

        try {
            $message = Message::fromArray($frame->payload);
        } catch (ValidationException $e) {
            return null;
        }

        if ($message->status === MessageStatus::EDITED) {
            return EventType::MESSAGE_EDIT;
        }
        if ($message->status === MessageStatus::REMOVED) {
            return EventType::MESSAGE_DELETE;
        }

        return EventType::MESSAGE_NEW;
    }

    private function resolveAttach(InboundFrame $frame): ?string
    {
        if ($frame->payload === null) {
            return null;
        }

        try {
            FileUploadSignal::fromArray($frame->payload);
            return EventType::FILE_READY;
        } catch (ValidationException $e) {
        }

        try {
            VideoUploadSignal::fromArray($frame->payload);
            return EventType::VIDEO_READY;
        } catch (ValidationException $e) {
        }

        return null;
    }
}
