from __future__ import annotations

from logging import getLogger
from typing import TYPE_CHECKING, Any

from pydantic import PrivateAttr, model_validator

from pymax.types.domain import Chat
from pymax.types.domain.base import CamelModel
from pymax.types.domain.message import Message

if TYPE_CHECKING:
    from pymax.api.messages import MessageService

logger = getLogger(__name__)


class MessageDeleteEvent(CamelModel):
    """Событие удаления сообщений.

    Handler ``on_message_delete`` получает этот объект, когда Max сообщает об
    удалении одного или нескольких сообщений в чате.

    :ivar chat: Чат, в котором удалены сообщения.
    :vartype chat: Chat
    :ivar message_ids: ID удаленных сообщений.
    :vartype message_ids: list[int]
    :ivar ttl: Признак удаления из-за TTL, если Max его прислал.
    :vartype ttl: bool
    """

    message_ids: list[int]
    chat_id: int
    chat: Chat | None = None
    message: Message | None = None
    ttl: bool = False

    _actions: MessageService | None = PrivateAttr(default=None)

    @model_validator(mode="before")
    @classmethod
    def normalize_payload(cls, data: Any) -> Any:
        # i really hate it cause of stupid web version thats send other type
        # of payload (128, expect 142)
        # TODO: impl it in the better way maybe

        if not isinstance(data, dict):
            return data

        if "chat" in data:  # case opcode == 142
            chat = data["chat"]

            return {
                "chat": chat,
                "ttl": data.get("ttl"),
                "messageIds": data["messageIds"],
                "chatId": chat["id"],
            }
        if "message" in data:  # case opcode == 128
            message = data["message"]
            return {
                "chatId": data["chatId"],
                "message": message,
                "ttl": data["ttl"],
                "messageIds": [message["id"]],
            }

        logger.warning("Illegal state during MessageDeleteEvent validation. Starting fallback")
        return data  # stupid fallback but who cares. Still better than KeyError

    def bind(self, actions: MessageService) -> MessageDeleteEvent:
        """Привязывает сервис сообщений к событию удаления."""
        self._actions = actions
        return self
