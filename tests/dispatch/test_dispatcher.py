from __future__ import annotations

import pytest

from pymax.dispatch import Dispatcher, Router
from pymax.dispatch.enums import EventType
from pymax.protocol import Command, Opcode
from tests.conftest import FakeApp, chat_payload, frame, message_payload


@pytest.mark.asyncio
async def test_dispatcher_routes_message_events_through_filters_and_raw_handler() -> (
    None
):
    app = FakeApp()
    router: Router[str] = Router()
    dispatcher: Dispatcher[str] = Dispatcher(app, router)
    dispatcher.bind_client("client")
    seen: list[tuple[str, int | str]] = []

    def is_start(message):
        return message.text == "/start"

    async def has_chat(message):
        return message.chat_id == 100

    @router.on_message(is_start, has_chat)
    async def on_message(message, client):
        seen.append((client, message.id))

    @router.on_raw()
    def on_raw(raw_frame, client):
        seen.append((client, f"raw:{raw_frame.opcode}"))

    await dispatcher.dispatch(
        frame(
            {"chatId": 100, "message": message_payload(1, 100, "/start")},
            opcode=Opcode.NOTIF_MESSAGE,
            cmd=Command.REQUEST,
        )
    )

    assert seen == [
        ("client", 1),
        ("client", f"raw:{int(Opcode.NOTIF_MESSAGE)}"),
    ]


@pytest.mark.asyncio
async def test_dispatcher_maps_chat_delete_and_internal_attach_events() -> (
    None
):
    app = FakeApp()
    router: Router[str] = Router()
    child: Router[str] = Router()
    router.include_router(child)
    dispatcher: Dispatcher[str] = Dispatcher(app, router)
    dispatcher.bind_client("client")
    seen: list[tuple[str, object]] = []

    @child.on_chat_update()
    async def on_chat(chat, _client):
        seen.append(("chat", chat.id))

    @router.on_message_delete()
    async def on_delete(event, _client):
        seen.append(("delete", tuple(event.message_ids)))

    @dispatcher.on_internal(EventType.FILE_READY)
    async def on_file(signal, _client):
        seen.append(("file", signal.file_id))

    await dispatcher.dispatch(
        frame(
            {"chat": chat_payload(5)},
            opcode=Opcode.NOTIF_CHAT,
            cmd=Command.REQUEST,
        )
    )
    await dispatcher.dispatch(
        frame(
            {"chat": chat_payload(5), "messageIds": [1, 2]},
            opcode=Opcode.NOTIF_MSG_DELETE,
            cmd=Command.REQUEST,
        )
    )
    await dispatcher.dispatch(
        frame({"fileId": 99}, opcode=Opcode.NOTIF_ATTACH, cmd=Command.REQUEST)
    )

    assert seen == [("chat", 5), ("delete", (1, 2)), ("file", 99)]


@pytest.mark.asyncio
async def test_dispatcher_requires_bound_client_for_callbacks() -> None:
    dispatcher: Dispatcher[str] = Dispatcher(FakeApp())

    async def callback(_event, _client):
        return None

    with pytest.raises(RuntimeError, match="client is not bound"):
        await dispatcher._call(callback, object())
