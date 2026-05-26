from __future__ import annotations

import pytest

from pymax.session.models import SessionInfo
from pymax.session.store import SessionStore
from pymax.types.domain.sync import SyncState


@pytest.mark.asyncio
async def test_session_store_saves_loads_updates_and_deletes_session(
    tmp_path,
) -> None:
    store = SessionStore(str(tmp_path), "test-session.db")
    session = SessionInfo(
        token="token-1",
        device_id="device-1",
        phone="+79990000000",
        mt_instance_id="mt-1",
        sync=SyncState(
            chats_sync=1,
            contacts_sync=2,
            drafts_sync=3,
            presence_sync=4,
            config_hash="hash-1",
        ),
    )

    await store.save_session(session)

    loaded = await store.load_session()
    by_device = await store.load_session_by_device_id("device-1")
    by_phone = await store.load_session_by_phone("+79990000000")

    assert loaded == session
    assert by_device == session
    assert by_phone == session

    await store.update_token("token-1", "token-2")
    loaded_after_update = await store.load_session()
    assert loaded_after_update is not None
    assert loaded_after_update.token == "token-2"

    await store.delete_session("token-2")
    assert await store.load_session() is None

    await store.close()
    assert store.conn is None
