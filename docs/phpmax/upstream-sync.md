# Upstream PyMax Sync

## Обязательное правило

Во время активной разработки проверять новые версии PyMax:

- перед началом каждого крупного milestone;
- перед изменениями protocol/auth/session/security;
- минимум раз в 7 календарных дней.

Если новая версия найдена, нельзя продолжать milestone вслепую. Нужно сначала
понять, влияет ли upstream diff на PHPMax.

## Источники проверки

- GitHub: `https://github.com/MaxApiTeam/PyMax`
- Python package: `maxapi-python`
- Текущий fork: `https://github.com/varyagnord/PHPMax`

## Процесс

1. Проверить latest tag/release/version upstream.
2. Сравнить с версией reference-кода в этом репозитории.
3. Просмотреть release notes и diff.
4. Отдельно проверить:
   - opcodes and commands;
   - TCP/WebSocket protocol;
   - auth/login/session/sync;
   - payload aliases and model fields;
   - dispatcher/event map;
   - uploads;
   - security-sensitive fixes.
5. Принять решение:
   - `port-now` - переносить до продолжения текущего milestone;
   - `port-later` - записать в backlog с причиной;
   - `ignore` - Python-only или не относится к PHPMax.
6. Обновить log ниже.

## Decision matrix

- Protocol/auth/session/security changes: default `port-now`.
- New service method: default `port-later`, если не блокирует текущий milestone.
- Docs-only upstream change: default `ignore`, если не меняет поведение.
- Python-only refactor: default `ignore`.
- Model/payload field change: default `port-now`, если поле участвует в уже
  перенесенном PHP функционале.

## Upstream Check Log

Записывать новые проверки в начало списка.

Template:

```text
Date: YYYY-MM-DD
Checked by:
Current PHPMax reference:
Latest upstream:
Sources checked:
Diff summary:
Decision:
Follow-up:
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local implementation/docs progress audit
Diff summary: no newer upstream version found during PHPMax completion-status audit
Decision: continue from local reference; remaining gaps are real-account integration, production validation and hardening, not upstream drift
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local src/pymax/__init__.py
Diff summary: no newer upstream version found before WebSocket UTF-8 text boundary hardening
Decision: continue from local reference; harden PHP WebSocket transport without changing Max payload contracts
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before proxy transport loopback coverage
Decision: continue from local reference; add deterministic HTTP CONNECT/SOCKS5 loopback tests without changing protocol contracts
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before WebSocket transport hardening
Decision: continue from local reference; harden PHP WebSocket transport boundary without changing PyMax payload contracts
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before API enum contract manifest expansion
Decision: continue from local reference; include service/API enum groups and payload key constants in contracts.json checks
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD refs/tags/v2.3.1
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before user-agent config parity transfer
Decision: continue from local reference; mirror PyMax app/device/locale anchors in PHP user-agent generation
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before contract manifest domain enum parity expansion
Decision: continue from local reference; include domain enum groups in contracts.json and strict PHP constant checks
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before CI/release workflow alignment
Decision: continue from local reference; align GitHub Actions with PHPMax just gates and release ZIP workflow
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before domain enum/nullability parity transfer
Decision: continue from local reference; add AccessType constants and preserve missing profileOptions as null
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before attachment model field parity transfer
Decision: continue from local reference; complete known attachment model fields and UnknownAttachment discriminator guard
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before internal dispatch/upload waiter parity transfer
Decision: continue from local reference; add App-level internal typed listeners before user router/raw dispatch and use them for upload processing notifications
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json via curl after PHP stream retry failed
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before profile App state parity transfer
Decision: continue profile state transfer from local reference; make changeProfile update App::me and shared user cache
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before lifecycle close/store cleanup transfer
Decision: continue App.close parity from local reference; close connection and session store through the runtime lifecycle boundary
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before App runtime state/cache parity transfer
Decision: continue App-level state transfer from local reference; move chat/user caches to App as the single runtime state owner
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before BaseClient state/relogin transfer
Decision: continue client state and relogin transfer from local reference; keep PHP API explicit with methods instead of Python properties
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before bounded runtime heartbeat transfer
Decision: continue ping behavior transfer from local reference; keep PHP heartbeat inside bounded runFor instead of a background loop
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before optional SQLite session store transfer
Decision: continue session persistence transfer from local reference; keep JSON default and add SQLite as optional parity backend
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before proxy adapter transfer
Decision: continue proxy foundation transfer from local reference; support proxy on TCP/WebSocket/upload boundaries
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before 2FA management transfer
Decision: continue auth/security 2FA transfer from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before WebSocket protocol/transport foundation
Decision: continue WebSocket foundation transfer from local reference; keep runtime bounded and TCP-compatible
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before QR auth contract transfer
Decision: continue auth QR foundation transfer from local reference; keep QR polling bounded for PHP runtime
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before optional telemetry transfer
Decision: continue telemetry transfer from local reference; keep PHP behavior bounded and explicit
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before bounded reconnect policy transfer
Decision: continue runtime reconnect implementation from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before Milestone 6 files/uploads transfer
Decision: continue upload service transfer from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
Diff summary: no newer upstream version found before user/account/folder service transfer
Decision: continue Milestone 5 user/account implementation from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
Diff summary: no newer upstream version found before Message/Chat domain-bound helper transfer
Decision: continue domain binding transfer from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
Diff summary: no newer upstream version found before router error/disconnect scope transfer
Decision: continue Milestone 4 router/dispatcher transfer from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
  - local pyproject.toml and src/pymax/__init__.py
Diff summary: no newer upstream version found before Milestone 4/5 event/chat work
Decision: continue event dispatcher and chat service transfer from local reference
Follow-up: next check before protocol/auth/session/security work or within 7 days
```

Initial record:

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: GitHub HEAD/tag v2.3.1, PyPI maxapi-python 2.3.1
Sources checked:
  - git ls-remote https://github.com/MaxApiTeam/PyMax.git HEAD/tags
  - https://pypi.org/pypi/maxapi-python/json
Diff summary: no newer upstream version found; local reference matches upstream HEAD and v2.3.1 tag
Decision: continue Milestone 0/1 implementation from local reference
Follow-up: upstream remote added as https://github.com/MaxApiTeam/PyMax.git
```

```text
Date: 2026-07-03
Checked by: Codex
Current PHPMax reference: maxapi-python 2.3.1, commit 8c40b71
Latest upstream: not rechecked after repository clone
Sources checked: local repository state
Diff summary: baseline documentation preparation only
Decision: before Milestone 0 implementation, perform full GitHub/Python package check
Follow-up: add upstream remote and record the first full sync audit
```
