from .auth import AuthMixin
from .chat import ChatMixin
from .message import MessageMixin
from .self import SelfMixin
from .user import UserMixin


class BaseMixin(
    SelfMixin,
    UserMixin,
    ChatMixin,
    MessageMixin,
    AuthMixin,
):
    """Собирает публичные API-методы клиента."""
