from pydantic import Field

from .base import CamelModel


class ElementAttributes(CamelModel):
    url: str


class Element(CamelModel):
    """Форматированный элемент текста сообщения.

    :ivar type: Тип элемента.
    :vartype type: str
    :ivar from_: Начальная позиция элемента.
    :vartype from_: int | None
    :ivar length: Длина элемента.
    :vartype length: int
    """

    type: str
    from_: int | None = Field(serialization_alias="from", default=None)
    length: int
    attributes: ElementAttributes | None = None
