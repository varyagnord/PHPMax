from .base import CamelModel


class MaxApiError(CamelModel):
    """Ошибка, возвращенная API Max.

    :ivar error: Код ошибки.
    :vartype error: str
    :ivar message: Сообщение ошибки.
    :vartype message: str
    :ivar title: Заголовок ошибки.
    :vartype title: str
    :ivar localized_message: Локализованное сообщение ошибки.
    :vartype localized_message: str | None
    """

    error: str
    message: str
    title: str
    localized_message: str | None
