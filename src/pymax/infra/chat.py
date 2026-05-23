from pymax.types import Chat, Message

from .protocol import IClientProtocol


class ChatMixin(IClientProtocol):
    """Методы клиента для чатов, групп, каналов и invite-ссылок."""

    async def create_group(
        self,
        name: str,
        participant_ids: list[int] | None = None,
        notify: bool = True,
    ) -> tuple[Chat, Message] | None:
        """Создает группу и возвращает чат со служебным сообщением.

        Args:
            name: Название группы.
            participant_ids: ID пользователей для добавления при создании.
            notify: Отправить ли участникам уведомление.

        Returns:
            ``(chat, message)`` или ``None``, если сервер не вернул данные
            созданного чата.
        """
        return await self._app.api.chats.create_group(
            name=name,
            participant_ids=participant_ids,
            notify=notify,
        )

    async def invite_users_to_group(
        self,
        chat_id: int,
        user_ids: list[int],
        show_history: bool = True,
    ) -> Chat | None:
        """Приглашает пользователей в группу.

        Args:
            chat_id: ID группы.
            user_ids: ID пользователей.
            show_history: Показать новым участникам историю сообщений.

        Returns:
            Обновленная группа или ``None``, если сервер не вернул чат.
        """
        return await self._app.api.chats.invite_users_to_group(
            chat_id=chat_id,
            user_ids=user_ids,
            show_history=show_history,
        )

    async def invite_users_to_channel(
        self,
        chat_id: int,
        user_ids: list[int],
        show_history: bool = True,
    ) -> Chat | None:
        """Приглашает пользователей в канал.

        Args:
            chat_id: ID канала.
            user_ids: ID пользователей.
            show_history: Показать новым участникам историю сообщений.

        Returns:
            Обновленный канал или ``None``, если сервер не вернул чат.
        """
        return await self._app.api.chats.invite_users_to_channel(
            chat_id=chat_id,
            user_ids=user_ids,
            show_history=show_history,
        )

    async def remove_users_from_group(
        self,
        chat_id: int,
        user_ids: list[int],
        clean_msg_period: int,
    ) -> bool:
        """Удаляет пользователей из группы.

        Args:
            chat_id: ID группы.
            user_ids: ID пользователей.
            clean_msg_period: Период очистки сообщений удаляемых участников.

        Returns:
            ``True``, если сервер принял запрос.
        """
        return await self._app.api.chats.remove_users_from_group(
            chat_id=chat_id,
            user_ids=user_ids,
            clean_msg_period=clean_msg_period,
        )

    async def change_group_settings(
        self,
        chat_id: int,
        all_can_pin_message: bool | None = None,
        only_owner_can_change_icon_title: bool | None = None,
        only_admin_can_add_member: bool | None = None,
        only_admin_can_call: bool | None = None,
        members_can_see_private_link: bool | None = None,
    ) -> None:
        """Обновляет настройки группы.

        Передавайте только те настройки, которые хотите изменить. ``None``
        оставляет конкретную настройку без изменений.

        Args:
            chat_id: ID группы.
            all_can_pin_message: Все участники могут закреплять сообщения.
            only_owner_can_change_icon_title: Только владелец меняет иконку
                и название.
            only_admin_can_add_member: Только администраторы добавляют людей.
            only_admin_can_call: Только администраторы начинают звонки.
            members_can_see_private_link: Участники видят приватную ссылку.
        """
        await self._app.api.chats.change_group_settings(
            chat_id=chat_id,
            all_can_pin_message=all_can_pin_message,
            only_owner_can_change_icon_title=only_owner_can_change_icon_title,
            only_admin_can_add_member=only_admin_can_add_member,
            only_admin_can_call=only_admin_can_call,
            members_can_see_private_link=members_can_see_private_link,
        )

    async def change_group_profile(
        self,
        chat_id: int,
        name: str | None,
        description: str | None = None,
    ) -> None:
        """Обновляет название и описание группы.

        Args:
            chat_id: ID группы.
            name: Новое название.
            description: Новое описание.
        """
        await self._app.api.chats.change_group_profile(
            chat_id=chat_id,
            name=name,
            description=description,
        )

    async def join_group(self, link: str) -> Chat:
        """Вступает в группу по пригласительной ссылке.

        Args:
            link: Полная invite-ссылка или ее часть с join-токеном Max.

        Returns:
            Группа, в которую вступил клиент.

        Raises:
            ValueError: Если строка не похожа на invite-ссылку.
        """
        return await self._app.api.chats.join_group(link)

    async def resolve_group_by_link(self, link: str) -> Chat | None:
        """Возвращает информацию о группе по invite-ссылке без вступления.

        Args:
            link: Полная invite-ссылка или ее часть с join-токеном Max.

        Returns:
            Группа или ``None``, если сервер не вернул чат.

        Raises:
            ValueError: Если строка не похожа на invite-ссылку.
        """
        return await self._app.api.chats.resolve_group_by_link(link)

    async def rework_invite_link(self, chat_id: int) -> Chat:
        """Перевыпускает приватную invite-ссылку группы.

        Args:
            chat_id: ID группы.

        Returns:
            Обновленная группа с новой ссылкой.
        """
        return await self._app.api.chats.rework_invite_link(chat_id)

    async def get_chats(self, chat_ids: list[int]) -> list[Chat]:
        """Возвращает чаты, используя кеш и догружая недостающие данные.

        Args:
            chat_ids: ID чатов в нужном порядке.

        Returns:
            Найденные чаты в порядке ``chat_ids``. Чаты, которых нет в
            ответе сервера, пропускаются.
        """
        return await self._app.api.chats.get_chats(chat_ids)

    async def get_chat(self, chat_id: int) -> Chat:
        """Возвращает чат по ID.

        Args:
            chat_id: ID чата.

        Returns:
            Найденный чат.

        Raises:
            PyMaxError: Если сервер не вернул чат.
        """
        return await self._app.api.chats.get_chat(chat_id)

    async def leave_group(self, chat_id: int) -> None:
        """Выходит из группы.

        Args:
            chat_id: ID группы.
        """
        await self._app.api.chats.leave_group(chat_id)

    async def leave_channel(self, chat_id: int) -> None:
        """Выходит из канала.

        Args:
            chat_id: ID канала.
        """
        await self._app.api.chats.leave_channel(chat_id)

    async def fetch_chats(self, marker: int | None = None) -> list[Chat]:
        """Загружает список чатов с сервера и обновляет кеш клиента.

        Args:
            marker: Маркер пагинации в миллисекундах. Если ``None``,
                используется текущий момент.

        Returns:
            Загруженные чаты.
        """
        return await self._app.api.chats.fetch_chats(marker=marker)
