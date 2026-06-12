from pymax.types.domain.base import CamelModel
from pymax.types.domain.message import ReactionCounter


class ReactionUpdateEvent(CamelModel):
    message_id: str
    chat_id: int
    counters: list[ReactionCounter]
    total_count: int
