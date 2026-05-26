from __future__ import annotations

import asyncio
from collections import deque
from types import SimpleNamespace

import pytest

from pymax.app import App
from pymax.auth.models import AuthResult
from pymax.exceptions import ApiError
from pymax.protocol import Command, InboundFrame, Opcode
from pymax.session.models import SessionInfo
from tests.conftest import frame, make_config, profile_payload


class RuntimeStore:
    def __init__(self, loaded: SessionInfo | None = None) -> None:
        self.loaded = loaded
        self.saved: list[SessionInfo] = []
        self.closed = False

    async def load_session(self) -> SessionInfo | None:
        return self.loaded

    async def save_session(self, session: SessionInfo) -> None:
        self.saved.append(session)
        self.loaded = session

    async def update_token(self, old_token: str, new_token: str) -> None:
        if self.loaded and self.loaded.token == old_token:
            self.loaded = self.loaded.model_copy(update={"token": new_token})

    async def close(self) -> None:
        self.closed = True


class RuntimeConnection:
    def __init__(self, responses: list[InboundFrame]) -> None:
        self.responses = deque(responses)
        self.protocol = SimpleNamespace(version=10)
        self.sent = []
        self.opened = False
        self.closed = False
        self.failed = None
        self.on_event = None
        self._seq = -1

    async def open(self) -> None:
        self.opened = True

    async def close(self) -> None:
        self.closed = True
        self.opened = False

    async def fail(self, exc: Exception | None = None) -> None:
        self.failed = exc

    async def request(self, outbound, timeout=None):
        self.sent.append((outbound, timeout))
        return self.responses.popleft()

    async def wait_closed(self) -> None:
        return None

    def next_seq(self) -> int:
        self._seq += 1
        return self._seq

    @property
    def is_open(self) -> bool:
        return self.opened


class StaticAuthFlow:
    async def authenticate(self, app: App) -> AuthResult:
        return AuthResult(token="auth-token")


@pytest.mark.asyncio
async def test_app_start_with_config_token_handshakes_logs_in_and_saves_session(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    async def idle_ping_loop(self):
        await asyncio.Event().wait()

    monkeypatch.setattr(App, "_ping_loop", idle_ping_loop)
    store = RuntimeStore()
    config = make_config().model_copy(
        update={"token": "config-token", "store": store}
    )
    connection = RuntimeConnection(
        [
            frame({}),
            frame(
                {
                    "profile": profile_payload(77),
                    "token": "login-token",
                    "contacts": [profile_payload(77)["contact"]],
                    "chats": [],
                    "messages": {},
                }
            ),
        ]
    )
    app: App[object] = App(connection, config, StaticAuthFlow())

    await app.start()

    assert app.started is True
    assert app.me is not None
    assert app.me.contact.id == 77
    assert store.saved[0].token == "config-token"
    assert [sent[0].opcode for sent in connection.sent] == [
        Opcode.SESSION_INIT,
        Opcode.LOGIN,
    ]

    await app.close()
    assert connection.closed is True
    assert store.closed is True


@pytest.mark.asyncio
async def test_app_invoke_turns_error_frames_into_api_error() -> None:
    store = RuntimeStore(
        SessionInfo(token="token", device_id="dev", phone="+7")
    )
    config = make_config().model_copy(update={"store": store})
    connection = RuntimeConnection(
        [
            InboundFrame(
                opcode=Opcode.PING,
                cmd=Command.ERROR,
                seq=1,
                payload={
                    "error": "rate_limited",
                    "title": "Slow down",
                    "message": "Too many requests",
                    "localizedMessage": "Too many requests",
                },
            )
        ]
    )
    app: App[object] = App(connection, config, StaticAuthFlow())

    with pytest.raises(ApiError) as exc_info:
        await app.invoke(Opcode.PING, {"interactive": True})

    assert exc_info.value.error == "rate_limited"
    assert exc_info.value.opcode == Opcode.PING
    assert connection.sent[0][0].seq == 0
