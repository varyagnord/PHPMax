from pymax.types.domain.base import CamelModel


class MessageReadEvent(CamelModel):
    set_as_unread: bool
    chat_id: int
    user_id: int
    mark: int
