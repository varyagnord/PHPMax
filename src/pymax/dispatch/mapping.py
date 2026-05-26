from __future__ import annotations

from collections.abc import Callable
from typing import TYPE_CHECKING

from pymax.protocol import InboundFrame, Opcode
from pymax.protocol.enums import Command
from pymax.types import Chat, MessageDeleteEvent
from pymax.types.domain import Message
from pymax.types.events import FileUploadSignal, VideoUploadSignal

from .enums import EventType
from .resolvers import (
    resolve_attach,
    resolve_chat,
    resolve_message,
    resolve_message_delete,
)

if TYPE_CHECKING:
    from pymax.app import App

Resolver = Callable[[InboundFrame], EventType | None]

EVENT_MAP: dict[Opcode, Resolver] = {
    Opcode.NOTIF_MESSAGE: resolve_message,
    Opcode.MSG_EDIT: resolve_message,
    Opcode.NOTIF_CHAT: resolve_chat,
    Opcode.NOTIF_MSG_DELETE: resolve_message_delete,
    Opcode.NOTIF_ATTACH: resolve_attach,
}


class EventResolver:
    def resolve(self, frame: InboundFrame) -> EventType | None:
        if frame.cmd != Command.REQUEST:
            return None

        try:
            opcode = Opcode(frame.opcode)
        except ValueError:
            return None

        handler = EVENT_MAP.get(opcode)

        if handler:
            return handler(frame)
        return None


class EventMapper:
    def __init__(self, app: App) -> None:
        self.app = app

    def map(self, event_type: EventType, frame: InboundFrame):
        if frame.cmd != Command.REQUEST:
            return None

        if frame.payload:
            if event_type in (EventType.MESSAGE_NEW, EventType.MESSAGE_EDIT):
                return Message.model_validate(frame.payload).bind(
                    self.app.api.messages
                )
            elif event_type == EventType.CHAT_UPDATE:
                return Chat.model_validate(frame.payload["chat"]).bind(
                    self.app.api.messages,
                    self.app.api.chats,
                )
            elif event_type == EventType.MESSAGE_DELETE:
                model = MessageDeleteEvent.model_validate(frame.payload)
                model.chat.bind(
                    self.app.api.messages,
                    self.app.api.chats,
                )
                return model
            elif event_type == EventType.VIDEO_READY:
                return VideoUploadSignal.model_validate(frame.payload)
            elif event_type == EventType.FILE_READY:
                return FileUploadSignal.model_validate(frame.payload)
        return frame
