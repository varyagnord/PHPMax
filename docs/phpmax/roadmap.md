# PHPMax Roadmap

## Milestone 0 - Preparation

- Создать `AGENTS.md`, `GEMINI.md` и `docs/phpmax`.
- Добавить upstream remote для `MaxApiTeam/PyMax`.
- Зафиксировать текущую reference-версию PyMax.
- Подготовить Composer skeleton и CI/test commands.

Status 2026-07-03: выполнено. Composer skeleton, just workflow, upstream
remote, upstream audit и GitHub Actions gates добавлены. CI запускает PHPMax
pre-publish checks на PHP 7.4/8.3 и сохраняет Python reference baseline.

## Milestone 1 - Contracts and Models

- Сгенерировать или вручную зафиксировать contract manifest из Python reference:
  opcodes, commands, event map, payload aliases, serialized payload keys,
  payload/domain/event model fields, public service/client methods and public
  service/client method parameters.
- Реализовать PHP 7.4-compatible constants вместо enums.
- Реализовать base model, hydrator, serializer, extra fields.
- Перенести core domain models: sync, session, auth responses, profile, user,
  chat, message, attachments, events.

Status 2026-07-03: частично выполнено. Contract manifest
`docs/phpmax/contracts.json` добавлен и проверяется через `just contract-check`.
Constants, включая domain enums и service/API enums, base model, sync/session,
profile/user/chat/message/elements/reactions/attachments, auth responses,
presence/member и typed event models добавлены. Contract check теперь сверяет
top-level serialized payload keys перенесенных PHP payload schemas с PyMax
reference, domain/response/attachment model keys и ловит отсутствующие PHP
equivalents для public PyMax API service methods и `Client` shortcuts для
PyMax infra mixins, а также drift в public parameter names/order.
`Name::firstName`/`lastName`, auth response aliases,
snake_case sync storage keys и attachment/download keys закреплены через
domain manifest audit. `changePassword($passwordOld, $passwordNew)` и
`Client::fetchHistory(..., $fromTime, ...)` закреплены signature gate.
Typed event model keys теперь тоже проверяются contract gate.
Hydrator validation приближен к Pydantic: required fields проверяются даже при
explicit empty input, malformed explicit collection values fail fast, and
PHP associative arrays are rejected where PyMax expects list payloads. Scalar
casting now keeps Pydantic-compatible coercions but rejects malformed scalar
payloads instead of using PHP permissive casts. Primitive list payload/domain
fields now validate both list shape and item values through `list<int>`,
`list<string>` and `list<mixed>` schemas, with `Profile::profileOptions` kept
as a server-compatible flexible array/map exception.
Unknown fields are now preserved only on `PHPMax\Domain\*` models to mirror
PyMax domain `extra="allow"`; API payload and session models ignore unknown
keys so they are not serialized back into protocol requests or storage.
Остается расширять model/payload parity fixtures по мере переноса новых API
участков.

## Milestone 2 - Protocol and Transport

- Перенести TCP frame models.
- Реализовать `TcpPacketFramer`.
- Реализовать MessagePack codec adapter.
- Реализовать LZ4 block decompression и optional Zstd adapter.
- Реализовать blocking TLS TCP transport с read/write timeouts.
- Покрыть byte-level parity fixtures.

Status 2026-07-03: частично выполнено. TCP frames, framer, MessagePack codec,
64-bit int fallback parity, LZ4, optional Zstd, blocking TCP transport и
parity tests добавлены.

## Milestone 3 - Runtime, Session, Auth

- Реализовать `ConnectionManager`, pending request resolution, seq wrap.
- Реализовать `App::invoke()`.
- Реализовать session stores: JSON first, SQLite optional.
- Перенести handshake, token login, SMS auth, 2FA password, registration config.
- Добавить bounded lifecycle: `open()`, `close()`, `runFor()`,
  `withOpenSession()`.

Status 2026-07-03: частично выполнено. ConnectionManager, App::invoke,
JsonFileSessionStore, optional SQLiteSessionStore, lifecycle anchors,
mobile/web handshake, token login, SMS auth flow, 2FA password challenge, 2FA management
(`setTwoFactor`/`removeTwoFactor`/`changePassword`/`checkTwoFactor`), token
refresh и disconnect callbacks для bounded runtime добавлены. Bounded reconnect
policy в `runFor()` добавлена: по умолчанию включена как в PyMax, но ограничена
execution budget. PyMax-like heartbeat `Opcode::PING` добавлен внутри bounded
`runFor()` и настраивается через `ClientOptions::pingInterval`. Public client
state anchors `me()`/`chats()`/`contacts()`/`messages()` и `relogin()` добавлены;
login response binds domain helpers, seeds chat/user caches и обновляет
persisted sync markers/`mtInstanceId` как PyMax `_update_session`; saved
sessions now reuse stored `mtInstanceId` during handshake instead of replacing
it with a fresh config id. `App` теперь является владельцем runtime state, а
chat/user services обновляют общий cache вместо независимых service-local
caches. Остались реальные интеграционные проверки. `Client::close()` теперь
проходит через `App::close()` и закрывает
transport вместе с session store. `AccountService::changeProfile()` обновляет
`App::me()` и общий user cache. Session stores поддерживают PyMax-like
`deleteAllSessions()` для full local cleanup. Auth response models now reject
empty payloads before hydration/session side effects like PyMax
`require_payload_model`.
Built-in JSON/SQLite stores теперь отклоняют path-like `sessionName`, чтобы
local session storage оставался внутри `workDir`.

## Milestone 4 - Messages and Events

- Перенести message service: send, get, edit, delete, history, reactions, read.
- Перенести markdown formatter с UTF-16 offsets.
- Реализовать router/dispatcher/filter/error/raw event behavior.
- Сохранить best-effort event mode для cron/short CLI.

Status 2026-07-03: частично выполнено. Message service для send/forward/get/
edit/history/delete/reactions/read, markdown formatter с UTF-16 offsets,
typed event resolver/mapper, typed router handlers, error scopes, disconnect
callbacks, raw fallback, domain-bound `Message` helpers и public `Client`
shortcuts для PyMax MessageMixin surface добавлены. Internal typed listeners
на уровне `App` теперь выполняются до пользовательского router-а и без raw
fallback. Error scopes теперь валидируют неизвестный scope fail-fast, чтобы
ошибка конфигурации не становилась глобальным handler-ом. Formatter parity
fixtures покрывают marker types, multiline code, invalid/multiline markers,
nested marker order, links и UTF-16 offsets после emoji. Message response
edge parity закреплен для strict send/forward/edit/read responses и nullable
file/video request helpers. Malformed `messages` list items теперь fail fast
как PyMax `parse_payload_list`. Reaction responses сохраняют optional empty
`reactionInfo` -> `null` behavior и fail-fast malformed/map checks.
Event mapper edge parity закреплен: falsey known payloads keep raw-frame
fallback, а truthy `CHAT_UPDATE` требует nested non-empty `chat`.

## Milestone 5 - Chats, Users, Account, Bots

- Перенести chat/user/self services.
- Перенести bot web app init data service.
- Перенести folder/session/account operations.
- Сверить cache/bind behavior domain objects.

Status 2026-07-03: начато. Chat service для create/join/resolve/get/fetch/
invite/remove/settings/profile/join requests/leave/delete добавлен вместе с
public `Client` shortcuts, service-local cache и domain-bound `Chat` helpers.
Chat service edge parity закреплен focused fixtures: `join/` trimming,
non-join channel links, invalid group resolve и PyMax-like `fetch_chats`
`marker or now` fallback для `0`, а также optional empty `chat` item as
missing behavior для create/invite/remove/join-request flows. Malformed
`chats` list items теперь fail fast как PyMax `parse_payload_list`.
User service, account/self service, folders, sessions list, contact import and
public `Client` shortcuts добавлены. Account profile photo object подключен
через `UploadService`. Bot web app init data service и public
`Client::getBotInitData()` добавлены. Folder/bot empty payload edge cases
закреплены PyMax-like fail-fast fixtures. `CONTACT_UPDATE` add/remove теперь
требует dict-like response до cache side effects, как PyMax `_contact_action`.
Malformed `contacts`/`sessions` list items теперь fail fast как PyMax
`parse_payload_list`.
Real account integration еще не запускалась.

## Milestone 6 - Files and Uploads

- Перенести `File`, `Photo`, `Video`.
- Реализовать photo multipart upload.
- Реализовать file/video streaming upload.
- Реализовать ожидание processing notification в bounded event loop.

Status 2026-07-03: начато. `PHPMax\Files\File`/`Photo`/`Video`, upload payload
models, `UploadService`, HTTP uploader abstraction, photo multipart upload,
file/video chunk upload, bounded `NOTIF_ATTACH` wait с internal waiter hooks,
public `Client` shortcuts, `MessageService` attachment object support и
profile photo upload в `AccountService::changeProfile()` добавлены. Photo HTTP
upload response edge cases закреплены PyMax-like `UploadException` fixtures, а
реальный multipart POST path проверяется loopback-тестом.
Download helper parity для `getFileById()`/`getVideoById()` и
`FileRequest`/`VideoRequest` моделей добавлен. Empty/malformed video/file
upload init responses теперь fail fast до HTTP upload. Direct cURL streaming
без предварительного temporary stream добавлен для file/video uploads и
закреплен loopback-тестом реального streaming POST. Photo
raw/path MIME теперь повторяет PyMax `image/<extension>` behavior, а URL MIME
остается guess/map behavior. URL source helpers принимают только absolute
`http`/`https` URL с host, а read/size/chunk helpers отклоняют non-2xx HTTP
responses как PyMax `raise_for_status()`. Empty raw sources fail on
read/size before HTTP upload. Domain helper fixtures покрывают
`Message::answer()`
и `Chat::answer()` с uploaded photo/video/file attachments. Upload waiters
теперь tracking-only для активных ids: non-matching attach events не копятся,
а active state очищается после успеха и HTTP upload failure. Добавлен
`just integration-check` как opt-in harness для реальных upload/download
проверок с `PHPMAX_TOKEN`, но реальные endpoints еще не запускались.

## Milestone 7 - Optional Layers

- WebSocket `WebClient` и QR auth.
- Proxy adapters.
- Telemetry.
- Release ZIP with vendor для shared hosting без shell composer install.

Status 2026-07-03: начато. Telemetry payload models, builder и service для
`Opcode::LOG` добавлены. `ClientOptions::telemetry` по умолчанию выключен; при
явном включении после успешного login отправляется bounded login event. PyMax
background telemetry loop не переносится буквально: в PHPMax telemetry остается
явной, короткоживущей и не должна ломать основной сценарий при ошибке отправки.
Navigation/open-chat payload helpers и bounded `NavigationPlanner` добавлены;
navigation session отправляется явно через `TelemetryService`, без фонового
sleep loop. QR auth foundation добавлен в auth layer:
`GET_QR`, `GET_QR_STATUS`, `LOGIN_BY_QR`, `AUTH_QR_APPROVE`, bounded
`QrAuthFlow` и `Client::authorizeQrLogin()`. WebSocket foundation добавлен:
runtime `FrameProtocolInterface`/`FrameReaderInterface`, `WsProtocol`,
`WebSocketFrameReader`, blocking `WebSocketTransport` со strict HTTP Upgrade
validation, frame hardening и `WebClient` scaffold с web user-agent и QR auth
flow по умолчанию. Proxy
foundation добавлен: `ClientOptions::proxy`, HTTP CONNECT/SOCKS5 connector для
TCP/WebSocket, loopback handshake fixtures и proxy propagation в
`NativeHttpUploader`. Release ZIP workflow
добавлен: `just release-zip` собирает runtime archive с fallback autoload,
`src/PHPMax`, docs/phpmax и optional `vendor`. `just integration-check` теперь
умеет запускать optional real WebSocket login, bot init data, telemetry login/
navigation, proxy и read checks при наличии env-параметров; без них эти
проверки безопасно пропускаются. `just integration-plan` показывает preflight
план real-account проверок без сетевых запросов и без вывода секретов. Реальная
WebClient/proxy/telemetry интеграционная проверка с аккаунтом еще не
запускалась.

## Definition of Done для каждого milestone

- Есть PHPUnit tests.
- Есть parity fixtures с Python reference.
- Обновлен `decision-log.md`, если принято новое решение.
- Проверка upstream PyMax не просрочена.
