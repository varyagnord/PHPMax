from pymax.types.domain.base import CamelModel


class TypingEvent(CamelModel):
    chat_id: int
    user_id: int
