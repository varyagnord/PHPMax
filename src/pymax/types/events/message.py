from __future__ import annotations

from typing import TYPE_CHECKING

from pydantic import PrivateAttr

from pymax.types.domain import Chat
from pymax.types.domain.base import CamelModel

if TYPE_CHECKING:
    from pymax.api.messages import MessageService


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

    chat: Chat
    message_ids: list[int]
    ttl: bool = False

    _actions: MessageService | None = PrivateAttr(default=None)

    def bind(self, actions: MessageService) -> MessageDeleteEvent:
        """Привязывает сервис сообщений к событию удаления."""
        self._actions = actions
        return self
