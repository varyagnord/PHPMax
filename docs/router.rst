Router
======

Что это
-------

Роутер хранит обработчики. Когда приходит новое событие, PyMax определяет его
тип, проверяет фильтры и вызывает подходящие handler-ы.

Зачем нужен
-----------

Можно писать обработчики прямо на клиенте:

.. code-block:: python

   from pymax import Client, Message

   client = Client(phone="+79990000000", work_dir="cache")


   @client.on_message()
   async def any_message(message: Message, client: Client) -> None:
       print(message.text)

А можно разнести логику по роутерам:

.. code-block:: python

   from pymax import Client, ClientRouter, Message

   router = ClientRouter()


   def is_start(message: Message) -> bool:
       return message.text == "/start"


   @router.on_message(is_start)
   async def start_command(message: Message, client: Client) -> None:
       await message.answer("Готово")


   client = Client(phone="+79990000000", work_dir="cache")
   client.include_router(router)

``Router``, ``ClientRouter`` и ``WebRouter``
--------------------------------------------

В PyMax есть один настоящий класс роутера: ``Router``. В
``src/pymax/routers.py`` дополнительно объявлены два alias-а типов:

.. code-block:: python

   ClientRouter = Router[Client]
   WebRouter = Router[WebClient]

Это не отдельные реализации и не разные режимы dispatch. Alias нужен, чтобы
подсказки типов понимали, какой клиент придет вторым аргументом handler-а.

Для ``Client`` можно писать так:

.. code-block:: python

   from pymax import Client, ClientRouter, Message

   router = ClientRouter()


   @router.on_message()
   async def handle(message: Message, client: Client) -> None:
       await message.answer("ok")

Для ``WebClient`` аналогично:

.. code-block:: python

   from pymax import Message, WebClient, WebRouter

   router = WebRouter()


   @router.on_message()
   async def handle(message: Message, client: WebClient) -> None:
       await message.answer("ok")

Корневой и вложенные роутеры
----------------------------

Root router
   Внутренний роутер клиента. Декораторы ``client.on_message()`` и
   ``client.on_start()`` регистрируют обработчики именно в нем. Его не нужно
   создавать руками.

Подключаемый router
   Отдельный ``Router``/``ClientRouter``/``WebRouter``, который вы создаете
   сами и подключаете через ``client.include_router(router)``.

Вложенный router
   Router, подключенный к другому router через ``router.include_router(child)``.
   Dispatch проходит по root router, затем по подключенным роутерам и их детям.

Фильтры
-------

Фильтр - это функция, которая получает событие и возвращает ``True`` или
``False``. Фильтры могут быть async.

.. code-block:: python

   from pymax import Client, ClientRouter, Message

   router = ClientRouter()


   def only_text(message: Message) -> bool:
       return bool(message.text)


   async def only_private(message: Message) -> bool:
       return message.chat_id is not None and message.chat_id > 0


   @router.on_message(only_text, only_private)
   async def handle(message: Message, client: Client) -> None:
       await message.answer("Текст получил")

Все фильтры должны вернуть ``True``. Если хотя бы один фильтр вернул ``False``,
handler не вызывается.

Почему handler принимает event и client
---------------------------------------

Handler всегда вызывается как ``handler(event, client)``. Это сделано, чтобы
один и тот же router можно было подключить к разным клиентам, а внутри handler
всегда был доступ к API-методам клиента.

.. code-block:: python

   from pymax import Client, Message

   @client.on_message()
   async def on_message(message: Message, client: Client) -> None:
       await message.answer("ok")

``on_start``
------------

``on_start`` вызывается после успешного подключения, авторизации и login,
когда уже доступны ``client.me`` и ``client.chats``.

.. code-block:: python

   from pymax import Client

   @client.on_start()
   async def started(client: Client) -> None:
       print(client.me)

Если включен reconnect, ``on_start`` будет вызван после каждого успешного
переподключения.

Raw events
----------

Если PyMax не смог распознать событие или вам нужны исходные данные, используйте
``on_raw``.

.. code-block:: python

   from pymax import Client
   from pymax.protocol import InboundFrame

   @client.on_raw()
   async def raw(frame: InboundFrame, client: Client) -> None:
       print(frame.opcode, frame.payload)

Другие события
--------------

Кроме новых и измененных сообщений, доступны специализированные декораторы:

* ``on_message_delete()`` и ``on_message_read()``;
* ``on_typing()`` и ``on_presence()``;
* ``on_reaction_update()`` и ``on_chat_update()``.

Все они поддерживают те же sync/async-фильтры и сигнатуру
``handler(event, client)``.

Частые ошибки
-------------

Забыли скобки у декоратора
   Нужно писать ``@client.on_message()``, а не ``@client.on_message``.

Handler принимает один аргумент
   Используйте ``async def handler(message: Message, client: Client) -> None: ...``.

Фильтр падает с ошибкой
   Handler не будет вызван, а ошибка попадет в логи dispatch.
