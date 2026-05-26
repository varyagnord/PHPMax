from __future__ import annotations

import pytest

from pymax.api.messages.enums import ItemType, MessagePayloadKey
from pymax.api.uploads.payloads import AttachPhotoPayload
from pymax.exceptions import UploadError
from pymax.files import File, Photo
from pymax.protocol import Opcode
from tests.conftest import FakeApp, frame, message_payload


@pytest.mark.asyncio
async def test_send_message_formats_markdown_uploads_attachments_and_binds_result(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr("pymax.api.messages.service.time.time", lambda: 1000.0)
    app = FakeApp([frame(message_payload(55, 100, "Hello bold"))])
    photo = Photo(raw=b"image", name="image.jpg")

    result = await app.api.messages.send_message(
        100,
        "Hello **bold**",
        reply_to=44,
        attachments=[photo],
        notify=False,
    )

    assert result is not None
    assert result.id == 55
    assert result._actions is app.api.messages
    assert app.api.uploads.calls == [("photo", photo)]
    assert app.calls[0].opcode == Opcode.MSG_SEND
    sent = app.calls[0].payload
    assert sent["chatId"] == 100
    assert sent["notify"] is False
    assert sent["message"]["text"] == "Hello bold"
    assert sent["message"]["cid"] == 1000001
    assert sent["message"]["link"] == {"type": "REPLY", "messageId": 44}
    assert sent["message"]["elements"][0]["type"] == "STRONG"
    assert sent["message"]["attaches"][0]["photoToken"] == "photo-token"


@pytest.mark.asyncio
async def test_send_message_raises_when_attachment_upload_fails() -> None:
    app = FakeApp()
    app.api.uploads.photo_result = None

    with pytest.raises(UploadError, match="Photo uploading failed"):
        await app.api.messages.send_message(
            100,
            "photo",
            attachments=[Photo(raw=b"image", name="image.jpg")],
        )

    assert app.calls == []


@pytest.mark.asyncio
async def test_upload_attachments_handles_file_video_and_empty_lists() -> None:
    app = FakeApp()
    assert await app.api.messages._upload_attachments(None) == []

    result = await app.api.messages._upload_attachments(
        [File(raw=b"abc", name="doc.txt")]
    )

    assert result[0].file_id == 30
    assert app.api.uploads.calls[0][0] == "file"


@pytest.mark.asyncio
async def test_fetch_history_builds_payload_and_parses_messages(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr("pymax.api.messages.service.time.time", lambda: 2000.0)
    app = FakeApp(
        [
            frame(
                {
                    MessagePayloadKey.MESSAGES.value: [
                        message_payload(1, 100, "one"),
                        message_payload(2, 100, "two"),
                    ]
                }
            )
        ]
    )

    messages = await app.api.messages.fetch_history(
        100,
        backward=2,
        from_=123,
        item_type=ItemType.DELAYED,
        get_chat=True,
        interactive=True,
    )

    assert [message.id for message in messages or []] == [1, 2]
    assert app.calls[0].opcode == Opcode.CHAT_HISTORY
    assert app.calls[0].payload["from"] == 123
    assert app.calls[0].payload["itemType"] == ItemType.DELAYED
    assert app.calls[0].payload["getChat"] is True


@pytest.mark.asyncio
async def test_delete_pin_and_read_message_send_expected_opcodes(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr("pymax.api.messages.service.time.time", lambda: 3000.0)
    app = FakeApp(
        [frame({}), frame({}), frame({"unread": 0, "mark": 3000000})]
    )

    assert (
        await app.api.messages.delete_message(100, [1, 2], for_me=True) is True
    )
    assert await app.api.messages.pin_message(100, 2, notify_pin=False) is True
    read_state = await app.api.messages.read_message(2, 100)

    assert read_state.mark == 3000000
    assert [call.opcode for call in app.calls] == [
        Opcode.MSG_DELETE,
        Opcode.CHAT_UPDATE,
        Opcode.CHAT_MARK,
    ]
    assert app.calls[0].payload["forMe"] is True
    assert app.calls[1].payload["pinMessageId"] == 2
    assert app.calls[2].payload["messageId"] == "2"


@pytest.mark.asyncio
async def test_reaction_methods_parse_optional_reaction_info() -> None:
    reaction_info = {
        "totalCount": 1,
        "counters": [{"count": 1, "reaction": "👍"}],
    }
    app = FakeApp(
        [
            frame({MessagePayloadKey.REACTION_INFO.value: reaction_info}),
            frame(
                {
                    MessagePayloadKey.MESSAGES_REACTIONS.value: {
                        "10": reaction_info,
                        "11": {"totalCount": 0, "counters": []},
                    }
                }
            ),
            frame({}),
        ]
    )

    added = await app.api.messages.add_reaction(100, "10", "👍")
    reactions = await app.api.messages.get_reactions(100, ["10", "11"])
    removed = await app.api.messages.remove_reaction(100, "10")

    assert added is not None
    assert added.total_count == 1
    assert reactions is not None
    assert reactions["10"].counters[0].reaction == "👍"
    assert removed is None
    assert [call.opcode for call in app.calls] == [
        Opcode.MSG_REACTION,
        Opcode.MSG_GET_REACTIONS,
        Opcode.MSG_CANCEL_REACTION,
    ]


@pytest.mark.asyncio
async def test_get_video_and_file_by_id_parse_request_models() -> None:
    app = FakeApp(
        [
            frame({"cache": True, "dynamicUrl": "https://video.test"}),
            frame({"unsafe": False, "url": "https://file.test"}),
        ]
    )

    video = await app.api.messages.get_video_by_id(100, 10, 20)
    file = await app.api.messages.get_file_by_id(100, "10", 30)

    assert video is not None
    assert video.url == "https://video.test"
    assert file is not None
    assert file.url == "https://file.test"
    assert [call.opcode for call in app.calls] == [
        Opcode.VIDEO_PLAY,
        Opcode.FILE_DOWNLOAD,
    ]
    assert app.calls[0].payload == {
        "chatId": 100,
        "messageId": 10,
        "videoId": 20,
    }
    assert app.calls[1].payload == {
        "chatId": 100,
        "messageId": "10",
        "fileId": 30,
    }


def test_next_cid_is_monotonic_when_clock_does_not_move(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setattr("pymax.api.messages.service.time.time", lambda: 10.0)
    app = FakeApp()
    app.api.messages._prev = 10000

    assert app.api.messages._next_cid() == 10001
    assert app.api.messages._next_cid() == 10002
