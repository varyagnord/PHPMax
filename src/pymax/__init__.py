__version__ = "2.1.2"


from .auth import (
    AuthFlow,
    ConsolePasswordProvider,
    ConsoleQrHandler,
    ConsoleSmsCodeProvider,
    PasswordProvider,
    QrAuthFlow,
    QrHandler,
    SmsAuthFlow,
    SmsCodeProvider,
)
from .client import Client
from .client_web import WebClient
from .config import ExtraConfig
from .dispatch import EventType, Router
from .exceptions import ApiError, PyMaxError, UploadError
from .files import File, Photo, Video
from .logging import configure_logging
from .routers import ClientRouter, WebRouter
from .types import Chat, Message, MessageDeleteEvent, Profile, User
from .types.domain.sync import SyncOverrides, SyncState

__all__ = (
    "ApiError",
    "AuthFlow",
    "Chat",
    "Client",
    "ClientRouter",
    "ConsolePasswordProvider",
    "ConsoleQrHandler",
    "ConsoleSmsCodeProvider",
    "EventType",
    "ExtraConfig",
    "File",
    "Message",
    "MessageDeleteEvent",
    "PasswordProvider",
    "Photo",
    "Profile",
    "PyMaxError",
    "QrAuthFlow",
    "QrHandler",
    "Router",
    "SmsAuthFlow",
    "SmsCodeProvider",
    "SyncOverrides",
    "SyncState",
    "UploadError",
    "User",
    "Video",
    "WebClient",
    "WebRouter",
    "__version__",
    "configure_logging",
)
