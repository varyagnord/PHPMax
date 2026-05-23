Account
=======

Что это
-------

Методы аккаунта работают с профилем текущего пользователя, папками чатов,
активными сессиями и выходом из аккаунта. Они доступны напрямую на клиенте.

Профиль
-------

После успешного login профиль текущего аккаунта лежит в ``client.me``:

.. code-block:: python

   @client.on_start()
   async def on_start(client: Client) -> None:
       if client.me is not None:
           print(client.me.contact.id)

Обновить имя, фамилию и описание:

.. code-block:: python

   await client.change_profile(
       first_name="Alex",
       last_name="PyMax",
       description="Testing Max API",
   )

Фотография профиля
------------------

Сейчас ``change_profile(photo=...)`` не загружает файл напрямую. Для фото
нужен ``photo_token`` от API Max:

.. code-block:: python

   upload_url = await client.request_profile_photo_upload_url()
   print(upload_url)

   await client.change_profile(
       first_name="Alex",
       photo_token="PHOTO_TOKEN",
   )

Если передать ``photo`` вместо ``photo_token``, PyMax выбросит
``NotImplementedError``.

Папки чатов
-----------

Создать папку:

.. code-block:: python

   update = await client.create_folder(
       title="Работа",
       chat_include=[123456, 234567],
   )

Получить список папок:

.. code-block:: python

   folders = await client.get_folders()
   for folder in folders:
       print(folder.id, folder.title)

Обновить и удалить папку:

.. code-block:: python

   await client.update_folder(
       folder_id="folder-id",
       title="Новый заголовок",
       chat_include=[123456],
   )

   await client.delete_folder("folder-id")

``FolderList`` можно итерировать напрямую: он перебирает ``folders`` внутри
ответа.

Сессии и выход
--------------

Получить активные сессии:

.. code-block:: python

   sessions = await client.get_sessions()
   for session in sessions:
       print(session.id, session.device_name, session.current)

Закрыть остальные сессии аккаунта:

.. code-block:: python

   await client.close_all_sessions()

Выйти из текущей сессии Max:

.. code-block:: python

   await client.logout()
   await client.close()

``logout()`` завершает серверную сессию. ``close()`` закрывает локальное
соединение, фоновые задачи и файл сессии.

Частые ошибки
-------------

``client.me`` равен ``None``
   Login еще не завершился или клиент не запущен. Читайте профиль внутри
   ``on_start`` или после успешного ``await client.start()`` в собственном
   lifecycle.

``NotImplementedError`` при ``change_profile(photo=...)``
   Прямая загрузка фото через параметр ``photo`` пока не реализована.
   Используйте ``photo_token``.

Папка создалась, но список старый
   Используйте ``get_folders()`` после изменения и сохраняйте новый
   ``folder_sync``, если строите собственную синхронизацию папок.
