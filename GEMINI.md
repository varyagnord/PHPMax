# AGENTS.md

## Главная цель

Мы превращаем Python-библиотеку PyMax в PHP-библиотеку PHPMax для PHP 7.4+.
Цель не в механическом переписывании файлов, а в аккуратном переносе поведения,
протоколов, payload-контрактов и developer experience с учетом ограничений PHP
и shared hosting.

## Обязательные правила работы

- Перед любыми существенными изменениями читать `docs/phpmax/README.md`,
  `docs/phpmax/roadmap.md`, `docs/phpmax/architecture.md` и
  `docs/phpmax/upstream-sync.md`.
- Для стандартных проверок использовать `just`. Перед публикацией запускать
  `just pre-publish-check`; для быстрой диагностики - `just doctor`.
- `src/pymax` считать reference-реализацией и источником правды. Не менять
  Python-код без отдельной причины: он нужен для сверки с upstream PyMax.
- PHP-код должен оставаться совместимым с PHP 7.4: не использовать native enum,
  union types, attributes, constructor property promotion, `match`, fibers и
  другие возможности PHP 8+.
- Основной runtime проектировать под shared hosting: короткие CLI/cron-запуски,
  bounded execution, явное закрытие соединений, сохранение session/sync.
- Realtime-события в первой версии поддерживаются best-effort, через
  ограниченные по времени `runFor()`/poll-циклы.
- Безопасность важнее удобства: токены не логировать, session-файлы держать вне
  webroot, использовать atomic write/file locks, давать понятные ошибки прав.
- Архитектуру держать слоистой: public client, app runtime, services, protocol,
  transport, models/hydration, session store. Не смешивать сетевой код,
  сериализацию и domain helpers.
- Для UI/документации всегда искать наиболее понятное решение. Помнить о
  законах Хика и Фиттса: меньше лишних выборов, очевидные действия, короткий
  путь к нужному сценарию.
- Документацию вести автоматически вместе с кодом. Если изменились исходники,
  перед публикацией обязательно оценить, нужно ли обновить docs. Если изменение
  косметическое и docs менять не нужно, явно зафиксировать, что docs были
  просмотрены и оставлены без изменений.
- `GEMINI.md` должен быть точной копией `AGENTS.md`. При изменении одного файла
  обязательно синхронизировать второй и проверять `cmp AGENTS.md GEMINI.md`.

## Обязательная проверка upstream PyMax

Перед началом каждого крупного этапа реализации и минимум раз в 7 дней во время
активной разработки нужно проверять, вышла ли новая версия основного Python
дистрибутива PyMax.

Порядок:

1. Проверить GitHub upstream `MaxApiTeam/PyMax` и опубликованный Python-пакет
   `maxapi-python`.
2. Сравнить текущую reference-версию в репозитории с upstream.
3. Проанализировать diff: opcodes, payloads, auth, session/sync, protocol,
   events, domain models, uploads, docs/release notes.
4. Принять решение: переносить изменение сейчас, отложить, или игнорировать как
   Python-only.
5. Записать результат в `docs/phpmax/upstream-sync.md`.

Если есть новая версия с изменениями protocol/auth/session/security, нельзя
начинать новый этап PHP-реализации, пока не решено, как эти изменения повлияют
на PHPMax.

## Основные якоря плана

- Staged parity: проектируем полный parity, реализуем этапами.
- Python reference остается рядом для будущего upstream merge.
- Composer-пакет PHPMax живет отдельным PSR-4 namespace `PHPMax\\`.
- TCP transport является приоритетом первой версии.
- WebSocket/QR `WebClient` переносится после TCP core как optional layer.
- Модели переносятся через явный hydration/serialization слой, а не ручной
  парсинг в каждом сервисе.
- Uploads и event dispatch считаются зонами повышенного риска и требуют
  отдельных parity-тестов.

## Проверки перед завершением задачи

- `git status --short` просмотрен.
- Если менялись инструкции, `AGENTS.md` и `GEMINI.md` идентичны.
- Если менялись docs, структура ссылок в `docs/phpmax/README.md` актуальна.
- Если менялись исходники, принято решение по документации: docs обновлены или
  осознанно не изменены как ненужные для данного изменения.
- Если менялся PHP-код, добавлены или обновлены PHPUnit/parity-тесты.
- Если менялся protocol/payload/model слой, сверены fixtures с Python reference.
- Перед публикацией выполнен `just pre-publish-check` либо явно указано, почему
  он не мог быть выполнен.
