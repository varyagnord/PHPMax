import logging
import re
import sys
from typing import TextIO

DATE_FORMAT = "%H:%M:%S"

RESET = "\x1b[0m"
DIM = "\x1b[2m"
BOLD = "\x1b[1m"

LEVEL_STYLES = {
    logging.DEBUG: ("\x1b[90m", "DEBUG"),
    logging.INFO: ("\x1b[36m", "INFO"),
    logging.WARNING: ("\x1b[33m", "WARN"),
    logging.ERROR: ("\x1b[31m", "ERROR"),
    logging.CRITICAL: ("\x1b[1;37;41m", "CRIT"),
}

LEVELS = {
    "CRITICAL": logging.CRITICAL,
    "FATAL": logging.FATAL,
    "ERROR": logging.ERROR,
    "WARNING": logging.WARNING,
    "WARN": logging.WARNING,
    "INFO": logging.INFO,
    "DEBUG": logging.DEBUG,
    "NOTSET": logging.NOTSET,
}


class PrettyFormatter(logging.Formatter):
    def __init__(self, *, use_colors: bool = True) -> None:
        super().__init__()
        self.use_colors = use_colors

    def format(self, record: logging.LogRecord) -> str:
        color, level = LEVEL_STYLES.get(record.levelno, ("", "???"))

        time = self.formatTime(record, DATE_FORMAT)
        message = record.getMessage()

        line = f"{DIM}{time}{RESET} {color}{BOLD}{level}{RESET} {message}"

        if record.exc_info:
            line += "\n" + self.formatException(record.exc_info)

        if not self.use_colors:
            line = _strip_ansi(line)

        return line


def configure_logging(
    level: int | str = logging.INFO,
    *,
    stream: TextIO | None = None,
    use_colors: bool | None = None,
) -> None:
    """Настраивает pretty-логи для logger-а ``pymax``.

    Обычно уровень логов задают через ``ExtraConfig(log_level="DEBUG")``.
    Вызывайте эту функцию вручную, если хотите управлять stream или цветами.

    Args:
        level: Уровень логирования: строка вроде ``"DEBUG"`` или число из
            модуля ``logging``.
        stream: Поток для вывода. По умолчанию ``sys.stderr``.
        use_colors: Включить ANSI-цвета. Если ``None``, определяется по TTY.

    Returns:
        ``None``.

    Example:
        .. code-block:: python

           from pymax import configure_logging

           configure_logging("DEBUG", use_colors=False)
    """
    stream = stream or sys.stderr

    if use_colors is None:
        use_colors = hasattr(stream, "isatty") and stream.isatty()

    logger = logging.getLogger("pymax")
    logger.handlers.clear()
    logger.setLevel(_normalize_level(level))
    logger.propagate = False

    handler = logging.StreamHandler(stream)
    handler.setLevel(_normalize_level(level))
    handler.setFormatter(
        PrettyFormatter(
            use_colors=use_colors,
        )
    )

    logger.addHandler(handler)


def get_logger(name: str | None = None) -> logging.Logger:
    if not name:
        return logging.getLogger("pymax")

    if name.startswith("pymax"):
        return logging.getLogger(name)

    return logging.getLogger(f"pymax.{name}")


def _normalize_level(level: int | str) -> int:
    if isinstance(level, int):
        return level

    value = LEVELS.get(level.upper())

    if isinstance(value, int):
        return value

    raise ValueError(f"Unknown log level: {level}")


def _strip_ansi(text: str) -> str:

    return re.sub(r"\x1b\[[0-9;]*m", "", text)


logging.getLogger("pymax").addHandler(logging.NullHandler())
