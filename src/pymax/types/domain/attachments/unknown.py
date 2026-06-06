from typing import Literal

from pydantic import Field

from pymax.types.domain.base import CamelModel

from .enums import AttachmentType


class UnknownAttachment(CamelModel):
    """Вложение неизвестного типа.

    :ivar type: Тип вложения.
    :vartype type: Literal[AttachmentType.UNKNOWN]
    """

    type: Literal[AttachmentType.UNKNOWN] = Field(alias="_type")
