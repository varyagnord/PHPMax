from __future__ import annotations

import pytest

from pymax.api.uploads.service import UploadService
from pymax.files import File, Photo, Video
from pymax.protocol import Opcode
from pymax.types.events import FileUploadSignal, VideoUploadSignal
from tests.conftest import FakeApp, frame


class FakeHttpResponse:
    def __init__(self, status: int, json_data: dict | None = None, on_enter=None) -> None:
        self.status = status
        self.json_data = json_data or {}
        self.on_enter = on_enter

    async def __aenter__(self):
        if self.on_enter is not None:
            self.on_enter()
        return self

    async def __aexit__(self, exc_type, exc, tb):
        return False

    async def json(self) -> dict:
        return self.json_data


class FakeHttpSession:
    posts: list[dict] = []
    response = FakeHttpResponse(200, {"photos": {"photo-1": {"token": "uploaded"}}})

    def __init__(self, *args, **kwargs) -> None:
        self.args = args
        self.kwargs = kwargs

    async def __aenter__(self):
        return self

    async def __aexit__(self, exc_type, exc, tb):
        return False

    def post(self, **kwargs):
        self.posts.append(kwargs)
        return self.response


@pytest.mark.asyncio
async def test_upload_photo_requests_url_posts_file_and_returns_attach_payload(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    app = FakeApp([frame({"url": "https://upload.test/path?photoIds=photo-1"})])
    service = UploadService(app)
    monkeypatch.setattr(
        "pymax.api.uploads.service.aiohttp.ClientSession",
        FakeHttpSession,
    )
    FakeHttpSession.posts = []
    FakeHttpSession.response = FakeHttpResponse(
        200,
        {"photos": {"photo-1": {"token": "uploaded"}}},
    )

    result = await service.upload_photo(Photo(raw=b"image-bytes", name="image.jpg"))

    assert result.photo_token == "uploaded"
    assert app.calls[0].opcode == Opcode.PHOTO_UPLOAD
    assert FakeHttpSession.posts[0]["url"] == "https://upload.test/path?photoIds=photo-1"


@pytest.mark.asyncio
async def test_upload_waiters_resolve_video_and_file_processing_signals() -> None:
    app = FakeApp()
    service = UploadService(app)
    loop = __import__("asyncio").get_running_loop()
    video_future = loop.create_future()
    file_future = loop.create_future()
    service.video_upload_waiters[1] = video_future
    service.file_upload_waiters[2] = file_future

    await service.on_video_attach(VideoUploadSignal(video_id=1), None)
    await service.on_file_attach(FileUploadSignal(file_id=2), None)

    assert video_future.result().video_id == 1
    assert file_future.result().file_id == 2
    assert service.video_upload_waiters == {}
    assert service.file_upload_waiters == {}


@pytest.mark.asyncio
async def test_upload_video_posts_chunks_waits_for_processing_and_cleans_waiter(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    app = FakeApp(
        [
            frame(
                {
                    "info": [
                        {
                            "url": "https://upload.test/video",
                            "videoId": 10,
                            "token": "video-token",
                        }
                    ]
                }
            )
        ]
    )
    service = UploadService(app)

    def resolve_processing() -> None:
        service.video_upload_waiters[10].set_result(VideoUploadSignal(video_id=10))

    FakeHttpSession.posts = []
    FakeHttpSession.response = FakeHttpResponse(200, on_enter=resolve_processing)
    monkeypatch.setattr(
        "pymax.api.uploads.service.aiohttp.ClientSession",
        FakeHttpSession,
    )

    result = await service.upload_video(Video(raw=b"video", name="clip.mp4"))

    assert result.video_id == 10
    assert result.token == "video-token"
    assert service.video_upload_waiters == {}
    assert FakeHttpSession.posts[0]["headers"]["Content-Range"] == "0-4/5"
    assert FakeHttpSession.posts[0]["url"] == "https://upload.test/video"


@pytest.mark.asyncio
async def test_upload_file_posts_chunks_waits_for_processing_and_cleans_waiter(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    app = FakeApp(
        [
            frame(
                {
                    "info": [
                        {
                            "url": "https://upload.test/file",
                            "fileId": 11,
                            "token": "file-token",
                        }
                    ]
                }
            )
        ]
    )
    service = UploadService(app)

    def resolve_processing() -> None:
        service.file_upload_waiters[11].set_result(FileUploadSignal(file_id=11))

    FakeHttpSession.posts = []
    FakeHttpSession.response = FakeHttpResponse(200, on_enter=resolve_processing)
    monkeypatch.setattr(
        "pymax.api.uploads.service.aiohttp.ClientSession",
        FakeHttpSession,
    )

    result = await service.upload_file(File(raw=b"file", name="doc.txt"))

    assert result.file_id == 11
    assert service.file_upload_waiters == {}
    assert FakeHttpSession.posts[0]["headers"]["Content-Range"] == "0-3/4"
    assert FakeHttpSession.posts[0]["url"] == "https://upload.test/file"
