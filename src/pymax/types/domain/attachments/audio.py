from typing import Literal

from pydantic import Field

from pymax.types.domain.base import CamelModel

from .enums import AttachmentType, TranscriptionStatus


class AudioAttachment(CamelModel):
    """Аудио-вложение сообщения.

    :ivar duration: Длительность аудио.
    :vartype duration: int
    :ivar audio_id: ID аудио.
    :vartype audio_id: int
    :ivar wave: Данные waveform.
    :vartype wave: str | None
    :ivar transcription_status: Статус транскрибации.
    :vartype transcription_status: TranscriptionStatus | None
    :ivar url: URL аудио.
    :vartype url: str | None
    :ivar type: Тип вложения.
    :vartype type: Literal[AttachmentType.AUDIO]
    :ivar token: Токен аудио.
    :vartype token: str | None
    """

    duration: int
    audio_id: int
    wave: str | None = None
    transcription_status: TranscriptionStatus | None = None
    url: str | None = None
    type: Literal[AttachmentType.AUDIO] = Field(alias="_type")
    token: str | None = None
