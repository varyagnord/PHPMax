# PHPMax Documentation Hub

Этот раздел описывает именно PHP-порт. Существующая RST-документация в `docs/`
остается reference-документацией PyMax и помогает сверять поведение.

## Быстрая навигация

- `architecture.md` - целевая архитектура PHPMax и границы слоев.
- `roadmap.md` - этапы реализации staged parity.
- `shared-hosting-runtime.md` - правила runtime под cron/short CLI и shared
  hosting.
- `upstream-sync.md` - обязательная проверка новых версий PyMax и принятие
  решений о переносе изменений.
- `documentation.md` - правила автоматического ведения документации и проверки
  перед публикацией.
- `contracts.md` - назначение и порядок обновления contract manifest.
- `contracts.json` - машинно проверяемый manifest opcodes/commands/events/
  payload anchors из Python reference.
- `release.md` - сборка release ZIP для shared hosting без composer install.
- `testing.md` - parity, unit и shared-hosting test matrix.
- `implementation-status.md` - что уже реализовано в PHPMax и что остается
  следующим.
- `decision-log.md` - зафиксированные архитектурные решения.
- Корневой `README.md` - публичный quick start PHPMax, который входит в
  release ZIP.

## Основные якоря

- PHPMax должен быть Composer-пакетом для PHP 7.4+.
- Python reference сохраняется рядом, чтобы регулярно подтягивать upstream
  PyMax и сверять контракты.
- Первая рабочая цель - TCP core: protocol, session, auth, messages, bounded
  event polling.
- Bounded runtime теперь включает PyMax-like heartbeat: `Client::runFor()`
  отправляет `Opcode::PING` по `ClientOptions::pingInterval`, а `0.0`
  отключает heartbeat явно.
- Public client state расширен до PyMax BaseClient anchors:
  `me()`, `chats()`, `contacts()`, `messages()`, `stop()` и `relogin()`.
- Runtime state теперь живет в `App`: login/fetch service results обновляют
  профиль и общие chat/user caches, а `Client` только отдает этот state наружу.
- Runtime dispatch включает internal typed listeners на уровне `App`: они
  получают события до пользовательского `Router` и без raw fallback.
- Lifecycle close теперь идет через `App::close()`: закрываются и transport, и
  session store.
- Message public shortcuts расширены до PyMax MessageMixin surface:
  forward/edit/history/delete/pin/reactions/read/download helpers доступны
  напрямую на `Client`.
- Files/uploads слой начат: `Photo`/`File`/`Video`, typed upload payload
  shortcuts, multipart/stream HTTP adapter и bounded `NOTIF_ATTACH` wait.
- Optional telemetry слой начат: явная отправка `Opcode::LOG`, без скрытого
  background loop, с выключенным по умолчанию автологином и bounded navigation
  planner для явной отправки NAV/PERF batches.
- QR auth foundation добавлен в auth layer: request/check/confirm/approve QR и
  bounded `QrAuthFlow`.
- WebSocket foundation начат: `WsProtocol`, message reader,
  hardened `WebSocketTransport` и `WebClient` scaffold поверх shared bounded
  runtime.
- Proxy foundation начат: `ClientOptions::proxy` прокидывается в TCP,
  WebSocket и HTTP uploads; поддержаны HTTP CONNECT и SOCKS5 adapters.
- Transport/runtime configuration fail-fast отклоняет empty endpoint host и
  port вне `1..65535`.
- Persistence foundation расширен: JSON store остается простым default, а
  `SQLiteSessionStore` добавлен как optional backend при наличии `pdo_sqlite`.
  Оба built-in store принимают только plain `sessionName`, без path segments.
- Release ZIP workflow добавлен: `tools/build-release.php`, `just release-zip`
  и fallback `autoload.php` внутри архива.
- Корневой `README.md` теперь описывает PHPMax, а не Python PyMax, и является
  частью release ZIP developer experience.
- Shared hosting - обязательное ограничение, поэтому long-running daemon не
  является единственным режимом работы.
- Модели, payloads и attachments переносить через общий hydration/serialization
  слой.
- Contract manifest фиксирует PyMax opcodes/commands/event map/domain enum
  values/API enum values/payload/domain/event anchors/serialized payload
  keys/service method surface/client mixin shortcuts, включая public parameter
  names/order, и проверяется через `just contract-check`.
- API error frames сохраняются как `ApiException` с opcode, error code,
  title/message/localizedMessage и исходным payload.
- Любое изменение upstream PyMax сначала анализируется, затем переносится или
  осознанно откладывается.
- Документация поддерживается вместе с кодом; при изменении исходников всегда
  проверяется, нужна ли актуализация docs.
- `just` является стандартной точкой входа для проверок: `just doctor`,
  `just pre-publish-check`, `just docs-guard`, `just integration-plan`,
  `just integration-check`.

## Как начинать новую задачу

1. Прочитать `AGENTS.md`.
2. Проверить `upstream-sync.md`: не просрочена ли обязательная upstream-проверка.
3. Найти нужный этап в `roadmap.md`.
4. Проверить архитектурные границы в `architecture.md`.
5. После изменения кода обновить тесты и документацию, если это влияет на
   поведение, API, архитектуру, установку, runtime или workflow.
6. Перед публикацией запустить `just pre-publish-check`.

## Just recipes

Основные рецепты:

- `just` или `just list` - показать доступные рецепты.
- `just doctor` - быстрые проверки инструкций, документационного guard-а и
  доступных инструментов.
- `just contract-check` - проверить `docs/phpmax/contracts.json` против
  `src/pymax` и PHP constants/event resolver.
- `just docs-guard` - проверить, что изменения исходников сопровождены
  документацией или осознанным решением ее не менять.
- `just php-check` - PHP lint, PHP 7.4 compatibility scan, contract manifest
  check, optional Composer validation и lightweight tests.
- `just release-check` - проверить release manifest/vendor policy без сборки
  ZIP.
- `just release-zip` - собрать `dist/phpmax-dev.zip` для shared hosting.
- `just integration-plan` - показать безопасный план real-account проверок и
  нужные env-переменные без сетевых запросов и без вывода секретов.
- `just integration-check` - optional real-account checks. По умолчанию
  безопасно пропускается; реальные TCP/WebSocket/upload/download/bot/telemetry
  проверки запускаются только при `PHPMAX_INTEGRATION=1` и нужных
  env-параметрах. Token login использует `PHPMAX_TOKEN`; first phone login
  проверяется через `PHPMAX_AUTH_SMS=1` и `PHPMAX_PHONE`, после чего harness
  проверяет сохраненную local session и повторный вход из нее. Перед сетью
  harness выполняет secret-safe preflight конфигурации.
- `DOCS_REVIEWED=1 just docs-guard` - разрешить публикацию мелкого изменения
  исходников без изменения docs после ручной проверки.
- `just pre-publish-check` - обязательная проверка перед публикацией в git.
