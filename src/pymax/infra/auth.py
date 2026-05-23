from pymax.api.auth.types import MISSING, Missing
from pymax.auth.providers import EmailCodeProvider

from .protocol import IClientProtocol


class AuthMixin(IClientProtocol):
    """Методы клиента для управления 2FA текущего аккаунта."""

    async def set_2fa(
        self,
        password: str,
        email: str | Missing = MISSING,
        hint: str | Missing = MISSING,
        email_code_provider: EmailCodeProvider | None = None,
    ) -> bool:
        """Устанавливает пароль 2FA для текущей учетной записи.

        Если ``email`` или ``hint`` не переданы, PyMax не добавляет
        соответствующую настройку. Если ``email`` передан без
        ``email_code_provider``, код подтверждения будет запрошен в консоли.

        Args:
            password: Новый пароль 2FA.
            email: Адрес электронной почты для 2FA, если требуется.
            hint: Подсказка пароля, если требуется.
            email_code_provider: Провайдер кода из электронной почты, если
                требуется.

        Returns:
            ``True``, если пароль успешно установлен.

        Raises:
            RuntimeError: Если установка пароля не удалась.
        """
        return await self._app.api.auth.set_2fa(
            password=password,
            email=email,
            hint=hint,
            email_code_provider=email_code_provider,
        )

    async def remove_2fa(self, password: str) -> bool:
        """Удаляет пароль 2FA для текущей учетной записи.

        Args:
            password: Текущий пароль 2FA.

        Returns:
            ``True``, если пароль успешно удален.

        Raises:
            RuntimeError: Если удаление пароля не удалось.
        """
        return await self._app.api.auth.remove_2fa(password=password)
