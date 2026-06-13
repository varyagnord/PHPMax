from pymax.types.domain.base import CamelModel
from pymax.types.domain.presence import Presence


class PresenceEvent(CamelModel):
    presence: Presence
    user_id: int
