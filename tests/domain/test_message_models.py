from __future__ import annotations

from pymax.types.domain import (
    AudioAttachment,
    Message,
    UnknownAttachment,
    VideoAttachment,
)
from tests.conftest import message_payload


def test_message_accepts_unsupported_attachment_type() -> None:
    payload = message_payload(1, 100)
    payload["attaches"] = [
        {
            "_type": "UNSUPPORTED",
            "duration": 259544,
            "token": "voice-token",
        }
    ]

    message = Message.model_validate(payload)

    attach = message.attaches[0]
    assert isinstance(attach, UnknownAttachment)
    assert attach.type == "UNSUPPORTED"
    assert attach.model_extra == {
        "duration": 259544,
        "token": "voice-token",
    }


def test_message_accepts_future_unknown_attachment_type() -> None:
    payload = message_payload(1, 100)
    payload["attaches"] = [
        {
            "_type": "VOICE_TRANSCRIPTION",
            "token": "future-token",
        }
    ]

    message = Message.model_validate(payload)

    attach = message.attaches[0]
    assert isinstance(attach, UnknownAttachment)
    assert attach.type == "VOICE_TRANSCRIPTION"
    assert attach.model_extra == {"token": "future-token"}


def test_audio_attachment_accepts_missing_server_fields() -> None:
    payload = message_payload(1, 100)
    payload["attaches"] = [
        {
            "_type": "AUDIO",
            "token": "audio-token",
        }
    ]

    message = Message.model_validate(payload)

    attach = message.attaches[0]
    assert isinstance(attach, AudioAttachment)
    assert attach.duration is None
    assert attach.audio_id is None
    assert attach.token == "audio-token"


def test_video_attachment_accepts_missing_duration() -> None:
    payload = message_payload(1, 100)
    payload["attaches"] = [
        {
            "_type": "VIDEO",
            "height": 720,
            "width": 1280,
            "videoId": 42,
            "previewData": b"preview",
            "thumbnail": "https://example.test/thumb.jpg",
            "token": "video-token",
            "videoType": 0,
        }
    ]

    message = Message.model_validate(payload)

    attach = message.attaches[0]
    assert isinstance(attach, VideoAttachment)
    assert attach.duration is None
    assert attach.video_id == 42


def test_message_elements_accept_missing_length_and_attribute_url() -> None:
    payload = message_payload(1, 100)
    payload["elements"] = [
        {
            "type": "ANIMOJI",
            "attributes": {},
        }
    ]

    message = Message.model_validate(payload)

    element = message.elements[0]
    assert element.length is None
    assert element.attributes is not None
    assert element.attributes.url is None
