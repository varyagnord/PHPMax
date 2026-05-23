import asyncio
from collections.abc import Awaitable, Callable
from contextlib import suppress

from pymax.logging import get_logger
from pymax.protocol import Command, InboundFrame, OutboundFrame
from pymax.protocol.base import BaseProtocol
from pymax.transport.base import Transport

from .pending import PendingRequests
from .readers.base import BaseReader

logger = get_logger(__name__)


class ConnectionManager:
    def __init__(
        self,
        reader: BaseReader,
        transport: Transport,
        protocol: BaseProtocol,
        on_event: Callable[[InboundFrame], Awaitable[None]] | None = None,
    ) -> None:
        self.reader = reader
        self.transport = transport
        self.protocol = protocol
        self.on_event = on_event

        self.requests = PendingRequests()

        self._is_open = False
        self._connection_lost = False
        self._seq = -1

        self._recv_task: asyncio.Task[None] | None = None
        self._event_tasks: set[asyncio.Task[None]] = set()

    async def open(self) -> None:
        if self._is_open:
            logger.debug("connection open skipped: already open")
            return

        logger.info("opening connection")
        await self.transport.connect()
        self._is_open = True
        self._connection_lost = False

        self._recv_task = asyncio.create_task(self._recv_loop())
        logger.debug("receive loop started")

    async def close(self) -> None:
        if not self._is_open and not self._recv_task:
            logger.debug("connection close skipped: already closed")
            return

        logger.info("closing connection")
        if self._recv_task:
            if not self._recv_task.done():
                self._recv_task.cancel()
            with suppress(asyncio.CancelledError, Exception):
                await self._recv_task
            logger.debug("receive loop stopped")
            self._recv_task = None

        for task in tuple(self._event_tasks):
            if not task.done():
                task.cancel()
        for task in tuple(self._event_tasks):
            with suppress(asyncio.CancelledError, Exception):
                await task
        self._event_tasks.clear()

        self.requests.cancel_all()
        await self.transport.close()
        self._is_open = False
        logger.info("connection closed")

    async def fail(self, exc: Exception | None = None) -> None:
        logger.warning("marking connection as failed")
        self._connection_lost = True
        self.requests.cancel_all(exc=exc)
        await self.transport.close()

    async def send(self, frame: OutboundFrame) -> None:
        if not self._is_open:
            logger.warning("send requested while connection is closed")
            raise ConnectionError("Connection is not open")

        data = self.protocol.encode(frame)
        logger.debug(
            "sending frame opcode=%s cmd=%s seq=%s bytes=%s",
            frame.opcode,
            frame.cmd,
            frame.seq,
            len(data),
        )
        await self.transport.send(data)

    async def request(
        self,
        frame: OutboundFrame,
        *,
        timeout: float | None = None,
    ) -> InboundFrame:
        future = self.requests.create(frame.seq)
        try:
            raw = self.protocol.encode(frame)
            logger.debug(
                "sending request opcode=%s cmd=%s seq=%s bytes=%s timeout=%s",
                frame.opcode,
                frame.cmd,
                frame.seq,
                len(raw),
                timeout,
            )
            await self.transport.send(raw)
            return await asyncio.wait_for(future, timeout)
        except Exception as e:
            logger.exception(
                "request failed seq=%s opcode=%s",
                frame.seq,
                frame.opcode,
            )
            self.requests.reject(frame.seq, e)
            raise

    async def wait_closed(self) -> None:
        if not self._recv_task:
            return

        await self._recv_task
        if self._connection_lost:
            raise ConnectionError("Connection lost")

    async def _recv_loop(self) -> None:
        logger.debug("receive loop entered")
        try:
            while True:
                frame = await self.reader.read()
                if frame is None:
                    logger.warning("reader returned empty frame")
                    continue

                logger.debug("received raw frame bytes=%s", len(frame))
                model = self.protocol.decode(frame)
                await self._handle_inbound(model)

        except EOFError as e:
            logger.warning("connection closed by server")
            self.requests.cancel_all(exc=ConnectionError("Connection closed by the server"))
            self._connection_lost = True
        except TimeoutError as e:
            logger.exception("connection timed out")
            self.requests.cancel_all(exc=ConnectionError("Connection timed out"))
            self._connection_lost = True
            raise e
        except Exception as e:
            logger.exception("connection receive loop failed")
            self.requests.cancel_all(exc=ConnectionError(f"Connection error: {e}"))
            self._connection_lost = True
            raise e

    async def _handle_inbound(self, frame: InboundFrame) -> None:
        logger.debug(
            "inbound frame opcode=%s cmd=%s seq=%s",
            frame.opcode,
            frame.cmd,
            frame.seq,
        )

        if (
            frame.cmd in (Command.RESPONSE, Command.ERROR)
            and frame.seq is not None
            and self.requests.resolve(frame.seq, frame)
        ):
            logger.debug("resolved pending request seq=%s", frame.seq)

        if self.on_event:
            task = asyncio.create_task(self._dispatch_event(frame))
            self._event_tasks.add(task)
            task.add_done_callback(self._event_tasks.discard)
        else:
            logger.debug("inbound event dropped: no event handler")

    async def _dispatch_event(self, frame: InboundFrame) -> None:
        if not self.on_event:
            return

        try:
            await self.on_event(frame)
        except Exception:
            logger.exception(
                "inbound event handler failed opcode=%s cmd=%s seq=%s",
                frame.opcode,
                frame.cmd,
                frame.seq,
            )

    def next_seq(self) -> int:
        self._seq = (self._seq + 1) % 0xFFFFFFFF
        return self._seq

    @property
    def is_open(self) -> bool:
        return self._is_open
