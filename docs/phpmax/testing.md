# PHPMax Testing Strategy

## Test groups

- Unit tests: small services, hydrator, formatter, JSON/SQLite session stores.
- Contract checks: generated PyMax manifest for opcodes, commands, dispatch
  event map, domain/API enums, payload/domain/event anchors, serialized
  payload keys and API service/client method surface.
- Release packaging tests: dry-run manifest and archive content.
- Byte parity tests: TCP frame header, MessagePack payloads, compression.
- MessagePack extension fixtures: MAX wrapped value code `1`, вложенные
  wrappers, неизвестный extension с byte-level round trip и fail-closed для
  поврежденного вложенного payload.
- Domain parity tests: Python fixture payload -> PHP model -> payload.
- Model alias parity tests: hydrator accepts PHP property names, protocol
  camelCase keys and PyMax/Pydantic snake_case field names.
- Hydrator validation tests: required-field validation runs even for explicit
  empty input, and explicit malformed array/list/map-list values fail instead
  of becoming empty collections; PHP associative arrays are rejected where
  PyMax expects list payloads; primitive `list<int>`/`list<string>`/
  `list<mixed>` payload fields validate list shape and item types; malformed
  scalar values fail instead of using PHP permissive casts; unknown fields are
  preserved for domain models but ignored by API payload/session models.
- Runtime tests: fake transport, pending request resolution, seq wrap.
- Runtime internal dispatch tests: internal typed listeners run before user
  router handlers and never receive raw fallback.
- API error tests: `Command::ERROR` payload -> `ApiException` with raw payload.
- WebSocket tests: JSON protocol, message reader, event-before-response flow,
  protocol version 11 in `App::invoke`, `WebClient` defaults, preservation of
  explicit web auth/user-agent options and byte-level `WebSocketTransport`
  hardening.
- User-agent tests: default/random Android and Web payloads stay within PyMax
  config anchors and web payload keeps the PyMax alias allowlist.
- Proxy tests: URL parsing, default ports, credentials, cURL/stream proxy
  normalization, unsupported scheme fail-fast, endpoint validation, direct
  timeout normalization and loopback HTTP CONNECT/SOCKS5 handshakes.
- Shared hosting tests: bounded `runFor()`, timeouts, clean close,
  session-store close, reconnect and lower-bound normalization for
  timeout/safety-margin options and direct transport/upload boundaries, plus
  host/port fail-fast validation.
- Runtime heartbeat tests: idle `runFor()` sends `Opcode::PING` with
  `interactive=true`, and `pingInterval=0.0` disables heartbeat.
- Lifecycle close tests: `Client::close()`, `withOpenSession()`,
  `relogin(false)` and unhandled startup failures close configured session
  store through `App::close()`.
- Public client shortcut tests: MessageMixin-compatible methods delegate to the
  service layer and preserve return models.
- Public client state tests: login response profile/chats/contacts/messages,
  bound domain helpers from login data, seeded caches and `relogin()` session
  reset.
- Domain binding tests: `Message`, `Chat`, `User` and `MessageDeleteEvent`
  receive their service bindings through the shared binder.
- App runtime state tests: shared chat/user caches are visible through `App`,
  updated by services and cleared by leave/delete/remove operations.
- Profile state tests: `changeProfile()` updates `AccountService::profile()`,
  `App::me()` and shared App user cache.
- Upload tests: fake TCP + fake HTTP, multipart/stream headers, bounded
  `NOTIF_ATTACH` wait, direct stream body reader, loopback cURL multipart and
  streaming POST, file source helpers with loopback URL status checks and typed
  `Client::upload*` shortcuts.
- Domain helper upload tests: bound `Message`/`Chat` methods with uploaded
  photo/video/file attachments and bounded file/video attach waits.
- Telemetry tests: payload builder parity, navigation planner, `Opcode::LOG`
  request payload, empty batch no-op, failure swallowing, optional auto-login
  telemetry.
- QR auth tests: request/check/confirm/approve QR opcodes and bounded
  immediate-confirmation `QrAuthFlow`.
- 2FA management tests: auth-track creation, password validation, email code,
  hint, set/remove/change 2FA payloads and profile option checks.
- Optional integration tests: real account/token through
  `just integration-check`, disabled by default and opt-in through
  `PHPMAX_INTEGRATION=1`.

## Required fixtures

- TCP packet with known header bytes.
- Contract manifest from Python reference: 4 commands, 164 opcodes, 13 event
  types, 9 dispatch map entries, 5 domain enum groups/25 values,
  16 API enum groups/47 values and 71 payload model anchors with field/key
  metadata, 46 domain/response/attachment model anchors with field/key
  metadata, plus 7 typed event model anchors with field/key metadata,
  8 API service domains/77 public method mappings/77 public parameter
  mappings and 59 public client mixin method mappings/59 public parameter
  mappings.
- Payload key aliases: `_type`, `mt_instanceid`, `from`, chat settings option
  keys and `_ContactPayload` -> PHP `ContactPayload` mapping.
- Core domain keys: `Name::firstName`/`lastName`, message reaction/previous-id
  keys, chat event counters and login/profile state keys.
- Hydrator edge keys: empty `Chat` input fails required-field validation,
  scalar `names`/`profileOptions`/login `messages` values fail collection
  validation, and `LoginResponse::contacts` allows `null` items but rejects
  scalar contact entries. Custom factories keep the same rule for
  `Message::attaches` and `MessageDeleteEvent::messageIds`: scalar attachment
  items and non-list/non-scalar delete ids fail fast. Associative arrays are
  rejected for list-like `names`, login `messages` map-list values, login
  `contacts`, message `attaches`, delete `messageIds`, upload `info`, and
  primitive-list request fields such as message ids, user ids, folder include
  ids and folder filters.
  Scalar coercion fixtures keep Pydantic-compatible int/bool coercion for
  `42.0`, `true` and `"false"`, while rejecting `"abc"`, `"42.1"`, arrays,
  non-string string fields and unknown boolean strings. Extra-field fixtures
  verify `Profile` preserves unknown domain fields, while
  `SendMessagePayload`/nested API payload models and `SessionInfo` ignore
  unknown keys so they do not leak into outgoing protocol payloads or storage.
- Response/storage/download keys: auth `TokenAttrs` uses `LOGIN`/`REGISTER`,
  `SyncState`/`SyncOverrides` keep snake_case storage keys, bot init data uses
  `queryId`, and `FileRequest`/`VideoRequest` keep download/playback URL keys.
- Attachment model keys: all known PyMax attachments are contract-checked for
  `_type`, id/token fields and aliases such as `videoType`, `photoToken`,
  `audioId`, `contactIds` and inline keyboard `keyboard`.
- Typed event keys: delete `messageIds`, read `setAsUnread`, reaction
  `totalCount`, file/video upload signal ids.
- API service method mappings: PyMax public service methods must have PHP
  equivalents with matching parameter names/order, including explicit PHP
  names for 2FA helpers.
- Client shortcut mappings: PyMax infra mixin methods must be exposed on
  `PHPMax\Client` with matching parameter names/order, including explicit PHP
  names for 2FA helpers.
- Signature anchors: `change_password(password_old, password_new)` maps to
  `changePassword($passwordOld, $passwordNew)`, and the public client
  `fetchHistory()` wrapper keeps `fromTime` to mirror PyMax `from_time`.
- MessagePack payloads with int keys, bytes keys, enums/constants, uint64
  timestamps and signed int64 values.
- LZ4 compressed payload.
- Zstd compressed payload if adapter exists.
- Message event payloads: new, edit, delete.
- Router/error scope fixtures: `global`/`local` error propagation, filter
  exceptions, invalid error scope fail-fast, disconnect callbacks and raw
  fallback after handled typed errors.
- Event mapper edge fixtures: falsey payloads for known events keep PyMax
  raw-frame fallback, while truthy `CHAT_UPDATE` payloads must contain a
  non-empty nested `chat` object and fail before raw fallback when malformed.
- Markdown formatter fixtures: all inline marker types, multiline code blocks,
  invalid/multiline markers, nested marker close order, links and UTF-16
  offsets after emoji/surrogate pairs.
- Message client shortcuts: forward/edit/history/delete/pin/reactions/read and
  file/video helper flow.
- Message response edge cases: `sendMessage()`/`forwardMessage()`/
  `readMessage()` require non-empty payloads, `editMessage()` requires a
  non-empty `message` item, while `getVideoById()`/`getFileById()` keep PyMax
  `parse_payload_model` nullable behavior on empty responses.
- Message list parsing edge cases: malformed `messages` items in
  `getMessages()` and `fetchHistory()` fail fast like PyMax
  `parse_payload_list`.
- Message reaction response edge cases: empty optional `reactionInfo` returns
  `null`, malformed `reactionInfo`/`messagesReactions` fail fast, and
  `messagesReactions` must be a message-id keyed map.
- Runtime reconnect: non-timeout disconnect, callback metadata, repeated
  `onStart`, event processing after reconnect.
- Runtime heartbeat: ping response matching through `ConnectionManager` and
  explicit disabled heartbeat path.
- API error frames: valid Max error payload and malformed fallback payload.
- Chat update with nested pinned message.
- Chat model payloads with `AccessType` values and snake_case counters/icons.
- Profile payloads where missing `profileOptions` stays `null` and is omitted
  from serialized payloads by default.
- Message model payloads with `{message: {...}}` wrappers, snake_case outer
  fields and snake_case nested attachment keys.
- Attachment discriminator fixtures for all known PyMax attachment types:
  photo, video, file, audio, contact, sticker, control, inline keyboard,
  share and call. Factory fixtures must cover both `_type` and `type`
  discriminator keys.
- Photo attachment payloads with PyMax fields (`photo_id`, `photo_token`,
  `base_url`, `preview_data`) and legacy `token` alias compatibility.
- `UnknownAttachment` fixtures: future `_type` is preserved with extra fields,
  but a known `_type` is rejected when parsed directly as unknown.
- Bot init data response: `queryId`, `url`.
- Auth responses: login token, registration token, password challenge.
- Auth response edge cases: response models that mirror PyMax
  `require_payload_model` reject empty payloads, including `AUTH_REQUEST` and
  `LOGIN`, and failed login responses must not update the session store.
- Login sync persistence: `LoginResponse::updateSyncState()` preserves saved
  markers when response `time`/`config.hash` are absent, updates all four sync
  markers plus config hash when present, and auth login saves the refreshed
  `mtInstanceId` with the updated sync state before token rotation.
- Client login state: profile, chats, contacts and chat-id keyed messages from
  `LOGIN`, saved-session handshake reusing stored `mtInstanceId`/`deviceId`,
  token login using fresh config ids when no session exists, plus relogin
  deletion of the refreshed session token.
- App cache state: login/fetch-created chats and users, cache hits, and cache
  removal for chat leave/delete and contact removal.
- User contact update response edge cases: `CONTACT_UPDATE` must return
  dict-like payload before add/remove contact side effects are accepted.
- User list parsing edge cases: malformed `contacts`/`sessions` items fail
  fast like PyMax `parse_payload_list` instead of being silently skipped.
- Chat service edge cases: join links are trimmed from the first `join/`
  prefix, `joinChannel()` keeps non-join channel links, invalid group resolve
  fails fast, `fetchChats(0)` mirrors PyMax `marker or now` behavior, and
  optional empty `chat` items are treated as missing instead of cached as
  empty domain objects.
- Chat list parsing edge cases: malformed `chats` items fail fast like PyMax
  `parse_payload_list` instead of being silently skipped.
- Account folder response edge cases: create/get/update/delete folder require
  a non-empty response payload like PyMax `require_payload_model`.
- Bot init data edge cases: empty `WEB_APP_INIT_DATA` response fails fast
  before hydrating `InitData`.
- Upload responses: photo, file, video, including invalid photo HTTP JSON,
  missing photo maps/tokens, missing or malformed video/file `info`, empty
  video/file init responses, empty upload URLs/tokens and non-positive upload
  ids rejected before HTTP upload starts.
- Native HTTP uploader: real cURL loopback fixtures must send multipart photo
  POST bodies with sanitized field/filename headers, and streaming file/video
  POST bodies through `CURLOPT_READFUNCTION` with the expected
  `Content-Length`, without relying on fake uploader behavior; upload
  endpoints must reject non-HTTP(S), scheme-relative, relative and invalid-port
  URLs before cURL/stream execution.
- File source helpers: `raw`, `path` and `url` name inference, local path
  size/read/chunk iteration, empty raw `read()`/`size()` failure, URL
  `read()`/`size()`/`iterChunks()` success and HTTP error status handling, URL
  scheme validation for absolute `http`/`https` only, redacted URL diagnostics
  without query/userinfo secrets, PyMax-like photo raw/path MIME and photo URL
  extension/MIME validation.
- Upload processing events: matching and non-matching `NOTIF_ATTACH`, pre-ready
  attach events dispatched during HTTP upload before blocking wait, and cleanup
  of active waiter state after success or HTTP upload failure.
- Domain helper upload attachments: bound `Message::answer()` and
  `Chat::answer()` with `Photo`, `Video` and `File` object inputs.
- Download/playback responses: `FileRequest`, `VideoRequest` with dynamic video
  URL key.
- Telemetry events: login, navigation, open_chat_to_render,
  open_chats_to_render.
- Telemetry navigation route: screen transitions, action ids, CHAT source
  params and CHATS main-tab params.
- WebSocket JSON frames: request, response, event and invalid JSON fallback.
- WebSocket transport hardening: HTTP `101` plus `Upgrade`/`Connection`/
  `Sec-WebSocket-Accept` validation, unmasked server frames, control frame
  limits, RSV rejection, valid fragmented text reassembly, invalid
  continuation/data interleaving failures, binary data frame rejection,
  invalid UTF-8 text rejection after full reassembly and oversized frame
  rejection before payload read.
- User-agent anchors: PyMax app versions/build numbers, Android device
  profiles, locale/timezone list, web version/screen and default browser
  header.
- 2FA payloads: `expectedCapabilities` order must match PyMax
  (`SET_PASSWORD`, `HINT`, `EMAIL`).
- Proxy URLs: HTTP CONNECT and SOCKS5 forms with and without credentials,
  including loopback handshake/tunnel byte fixtures and delayed HTTP CONNECT
  response under direct negative timeout normalization.
- JSON/SQLite session stores: token, device_id, phone, mt_instance_id and sync
  marker persistence compatible with PyMax store, lookup by device/phone,
  token update, delete by token, `deleteAllSessions()` full local cleanup and
  unsafe file-name rejection for empty names, `.`/`..`, path separators and
  null bytes.
- Session token rotation: `closeAllSessions()` updates the stored token without
  losing device, phone, mt_instance_id or sync marker state.
- Release ZIP content: fallback `autoload.php`, `src/PHPMax`, `docs/phpmax`,
  no `src/pymax`, no tests, plus extracted-archive smoke test for runtime
  class loading without Composer across public clients, transports, JSON
  session store and file/photo helpers. Root `README.md` content is checked in
  the source tree and extracted archive so PHPMax releases cannot regress to
  Python/PyMax installation instructions.

## Commands target

`just` является основной точкой входа для локальных проверок:

```bash
just doctor
just contract-check
just php-check
just release-check
just release-zip
just integration-plan
just integration-check
just pre-publish-check
```

Текущий `just php-check` работает даже без Composer:

- lint всех PHP-файлов в `src/PHPMax`, `tests-php`, `tools`;
- `php tools/php74-compat-check.php` ловит native enum, union types,
  attributes, constructor property promotion, `match`, nullsafe operator,
  PHP 8-only return/property/parameter types и PHP 8-only helper calls;
- `php tools/contract-manifest.php check`;
- `composer validate --strict`, если Composer установлен;
- lightweight test runner `php tools/run-php-tests.php`.

`just release-check` запускает `php tools/build-release.php --check` и
проверяет release manifest/vendor policy без создания ZIP. Реальная сборка
архива остается отдельной командой `just release-zip`.

Optional real-account checks are intentionally outside `php-check` and CI:

```bash
just integration-plan
PHPMAX_INTEGRATION=1 PHPMAX_TOKEN=... just integration-check
PHPMAX_INTEGRATION=1 PHPMAX_AUTH_SMS=1 PHPMAX_PHONE=+79990000000 just integration-check
```

`just integration-plan` performs no network requests, does not require
`PHPMAX_TOKEN`, and prints only check names/env variable names. Token/proxy
values must never be printed by the harness.

When `PHPMAX_INTEGRATION=1`, the harness performs a preflight before any
network request. It validates session names, numeric env parsing/bounds,
upload path readability, writable workdir and `Client`/`WebClient`
construction, including proxy config, and returns exit code `2` for malformed
configuration without printing token/proxy secret values.

Base run performs TCP login/profile-state verification. It can use either
`PHPMAX_TOKEN` or interactive SMS auth through `PHPMAX_AUTH_SMS=1` and
`PHPMAX_PHONE`. In SMS mode the harness asks for the code through STDIN, then
verifies that a local session was saved and immediately opens a second client
from that saved session without token/SMS auth flow. Additional checks are
enabled by env flags/ids:

- `PHPMAX_FETCH_CHATS=1`, `PHPMAX_FETCH_SESSIONS=1`;
- `PHPMAX_TELEMETRY_LOGIN=1`, `PHPMAX_TELEMETRY_NAVIGATION=1`;
- `PHPMAX_BOT_ID`, optional `PHPMAX_BOT_CHAT_ID`,
  `PHPMAX_BOT_START_PARAM`;
- `PHPMAX_UPLOAD_PHOTO=1`, `PHPMAX_UPLOAD_FILE=1`,
  `PHPMAX_UPLOAD_VIDEO=1` with `PHPMAX_UPLOAD_VIDEO_PATH`;
- `PHPMAX_DOWNLOAD_CHAT_ID`, `PHPMAX_DOWNLOAD_MESSAGE_ID`,
  `PHPMAX_DOWNLOAD_FILE_ID`/`PHPMAX_DOWNLOAD_VIDEO_ID`;
- `PHPMAX_WEBSOCKET=1`;
- `PHPMAX_PROXY` to run the same checks through proxy.

The harness stores session data in `PHPMAX_WORKDIR` or
`sys_get_temp_dir()/phpmax-integration` and never prints tokens or proxy
credentials.

Результат PHP portion последнего локального `just pre-publish-check` на
2026-07-03:

```text
PHP 7.4 compatibility check passed.
Contract manifest is in sync.
Composer is not installed; skipping composer validate.
.............................
Assertions: 1303
OK
Release spec is valid.
```

Когда Composer будет установлен в окружении, дополнительно должны быть доступны:

```bash
composer validate
composer test
composer phpstan
```

Если проверяется PHP 7.4 совместимость на машине с новой PHP-версией, Composer
должен использовать platform config `php: 7.4.x`, а lightweight gate дополнительно
запускает `tools/php74-compat-check.php`.

## CI target

GitHub Actions `tests.yml` должен зеркалировать локальный gate:

- PHPMax job запускает `just pre-publish-check` на PHP 7.4 и 8.3;
- Composer только валидирует `composer.json`, runtime dependencies не
  устанавливаются для lightweight runner;
- release manifest/vendor policy проверяются через `just release-check`;
- Python reference lint/test baseline остается отдельными jobs, чтобы
  `src/pymax` не деградировал как источник parity.

## Acceptance rule

Нельзя считать milestone завершенным, если измененный behavior не покрыт хотя бы
одним тестом или fixture. Для protocol/model/payload слоя parity fixture
обязателен.

Перед публикацией в git обязательно выполнить `just pre-publish-check`. Если
исходники изменились без изменения документации, нужно либо обновить docs, либо
после ручной оценки запустить `DOCS_REVIEWED=1 just docs-guard` и явно указать,
почему документация не требовалась.
