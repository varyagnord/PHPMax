Chats
=====

Что это
-------

``Chat`` описывает диалог, группу или канал Max. У клиента есть кеш чатов из
login/sync, а также методы для загрузки, создания и управления чатами.

Получить чаты
-------------

После ``start`` доступны:

.. code-block:: python

   from pymax import Client

   @client.on_start()
   async def on_start(client: Client) -> None:
       for chat in client.chats or []:
           print(chat.id, chat.title)

Если нужного чата нет в ``client.chats``, запросите его явно:

.. code-block:: python

   chat = await client.get_chat(123456)
   print(chat.title)

Загрузить список чатов с сервера:

.. code-block:: python

   chats = await client.fetch_chats()
   for chat in chats:
       print(chat.id, chat.title)

Почему не видно чаты
--------------------

``client.chats`` - это не гарантированная полная база всех чатов. Это список,
который Max вернул на login с учетом sync-state. На повторном запуске PyMax
отправляет сохраненный ``chats_sync``, и сервер может вернуть только изменения.

Что делать:

* вызовите ``await client.fetch_chats()``;
* запросите конкретный чат через ``await client.get_chat(chat_id)``;
* для следующего login используйте ``SyncOverrides(chats_sync=-1)``;
* проверьте, что вы авторизованы тем аккаунтом и тем файлом сессии.

Создать группу
--------------

.. code-block:: python

   result = await client.create_group(
       name="PyMax test",
       participant_ids=[111, 222],
   )
   if result is not None:
       chat, service_message = result

Пригласить и удалить участников
-------------------------------

.. code-block:: python

   await client.invite_users_to_group(
       chat_id=123456,
       user_ids=[111, 222],
       show_history=True,
   )

   await client.remove_users_from_group(
       chat_id=123456,
       user_ids=[222],
       clean_msg_period=0,
   )

Через объект ``Chat``:

.. code-block:: python

   chat = await client.get_chat(123456)
   await chat.invite([111])
   await chat.answer("Добро пожаловать")

``invite()`` работает только для групп и каналов. Для личного диалога тип чата
не подходит, и метод завершится ошибкой.

Настройки и профиль группы
--------------------------

.. code-block:: python

   await client.change_group_profile(
       chat_id=123456,
       name="Новый заголовок",
       description="Описание группы",
   )

   await client.change_group_settings(
       chat_id=123456,
       only_admin_can_add_member=True,
       only_admin_can_call=True,
   )

Через объект ``Chat`` можно менять настройки и перевыпускать invite-ссылку:

.. code-block:: python

   chat = await client.get_chat(123456)
   await chat.update_settings(only_admin_can_add_member=True)
   updated = await chat.rework_invite_link()

История сообщений
-----------------

.. code-block:: python

   chat = await client.get_chat(123456)
   messages = await chat.history(
       backward=50,
       from_time=None,
   )

``from_time`` - точка отсчета в Unix time **в миллисекундах**. Если передать
``None``, PyMax использует текущий момент. ``backward_time`` и
``forward_time`` тоже передаются в миллисекундах и задают временное окно назад
или вперед от ``from_time``.

Выйти из чата
-------------

.. code-block:: python

   chat = await client.get_chat(123456)
   await chat.leave()

``leave()`` зависит от типа чата: для группы вызывает выход из группы, для
канала - выход из канала. Из личного диалога выйти нельзя.

Invite-ссылки
-------------

.. code-block:: python

   chat = await client.resolve_group_by_link("https://max.ru/join/...")
   joined = await client.join_group("https://max.ru/join/...")
   updated = await client.rework_invite_link(joined.id)

Частые ошибки
-------------

``Cannot leave dialog``
   Из личного диалога нельзя выйти как из группы или канала.

``Unknown chat type``
   Сервер прислал неизвестный тип чата или в объекте нет поля ``type``.

``PyMaxError: Chat not found``
   Сервер не вернул чат. Проверьте ID, права доступа и аккаунт.
