from enum import Enum


class EventType(str, Enum):
    MESSAGE_NEW = "message_new"
    MESSAGE_EDIT = "message_edit"
    MESSAGE_DELETE = "message_delete"
    TYPING = "typing"
    CHAT_UPDATE = "chat_update"
    USER_UPDATE = "user_update"
    VIDEO_READY = "video_ready"
    FILE_READY = "file_ready"
    RAW = "raw"
