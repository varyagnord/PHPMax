# PHPMax Architecture

## Целевая форма пакета

PHPMax проектируется как чистый Composer-пакет с PSR-4 namespace `PHPMax\\`.
Python-код в `src/pymax` остается reference implementation и не является частью
PHP autoload.

Release ZIP для shared hosting является build artifact: он содержит PHP runtime
слой, docs и optional `vendor`, но не включает Python reference, tests или
tooling.

## Слои

1. Public API:
   `Client`, `WebClient`, `Router`, domain models, file helpers.
2. Runtime:
   `App`, lifecycle, bounded execution, reconnect policy, heartbeat ping,
   dispatcher.
3. Services:
   auth, session, messages, chats, users, self/account, uploads, bots,
   telemetry.
4. Files and HTTP upload:
   `File`/`Photo`/`Video` value objects, upload payloads,
   `HttpUploaderInterface`, bounded processing wait.
5. Protocol:
   frames, commands, opcodes, TCP framing, MessagePack, compression adapters,
   WebSocket JSON protocol, contract manifest checks.
6. Transport:
   blocking TLS TCP streams first, blocking WebSocket transport, proxy adapters.
7. Models:
   hydration, aliases, defaults, domain-only unknown extra fields, attachment
   discriminator.
8. Persistence:
   session store adapters, sync state, token update, safe file permissions.

## Что переносить буквально

- Opcode values.
- Command values.
- TCP header layout.
- Sync/session semantics.
- Payload keys and aliases.
- Mobile/web user-agent generation anchors: app versions, Android device
  profiles, locale/timezone list, web app version and header user-agent.
- Hydrator input compatibility: PyMax/Pydantic snake_case field names,
  protocol camelCase keys and explicit aliases должны приниматься одинаково.
- Domain enum export parity: PyMax enum-like values должны иметь PHP 7.4
  constant classes, даже если runtime хранит их как строки.
- Optional field nullability: если PyMax default равен `None`, PHP model не
  должен подменять отсутствующее поле пустой коллекцией без явной причины.
- Attachment discriminator parity: каждый известный `_type` должен иметь
  concrete PHP model с PyMax fields; discriminator принимает `_type` и `type`;
  `UnknownAttachment` предназначен только для future/unknown types.
- Event resolution rules.
- Error payload shape.
- Contract anchors from `docs/phpmax/contracts.json`, including protocol values,
  event routing, domain enum values, API enum values, response payload key
  values, payload/domain/event model fields, serialized payload keys and public
  API service method/public client shortcut surface, including public
  parameter names/order.

## Что переносить PHP-идиоматично

- Python decorators заменить явными `Router::onMessage($handler, ...$filters)`.
- Pydantic заменить schema-driven hydrator.
- Schema-driven hydrator сериализует canonical protocol keys, но на входе
  принимает и PHP property names, и PyMax snake_case field names. Специальные
  `normalizeInput()` wrappers не должны затирать вложенные значения `null`-ом,
  если outer field реально отсутствует.
- Explicit model input must be validated like Pydantic: `fromArray([])` fails
  for models with required fields, and explicit scalar values for array/list/
  map-list fields fail instead of being silently converted to empty
  collections. Defaults apply only to absent optional fields. PHP arrays used
  for `list<...>` fields must be list-like (`0..n` integer keys); associative
  arrays remain maps and must not be accepted where PyMax expects a list.
  Primitive list schemas (`list<int>`, `list<string>`, `list<bool>`,
  `list<array>`, `list<mixed>`) validate every item through the same guarded
  casting rules as scalar fields. `Profile::profileOptions` stays a flexible
  array/map exception because live profile fixtures may arrive as option maps
  instead of PyMax's annotated list form.
  Scalar coercion must also stay Pydantic-like: numeric integer strings and
  integer-compatible floats are accepted for `int`, common boolean strings are
  accepted for `bool`, but malformed scalar values must fail instead of using
  PHP's permissive casts.
- Unknown fields are preserved only for `PHPMax\Domain\*` models, matching
  PyMax `domain/base.py` `extra="allow"`. `PHPMax\Api\*` payload models and
  `PHPMax\Session\*` storage models ignore unknown keys like PyMax default
  Pydantic models, so future or malformed input does not leak back into
  outgoing protocol payloads or persisted session rows.
- Async/await заменить bounded blocking runtime с timeouts.
- Python mixins заменить thin methods на `Client`, делегирующие в services.
- PyMax BaseClient properties переносить как явные PHP methods:
  `me()`, `chats()`, `contacts()`, `messages()`. Объекты из login response
  должны быть bound к services и seeded в `App` runtime state.
- `App` является владельцем runtime state (`me`, `chats`, `users`, `contacts`,
  `messages`). Services не должны держать независимые долгоживущие caches,
  которые могут разойтись с login/sync/profile state.
- PyMax internal dispatcher переносится как internal typed listeners в `App`:
  `ConnectionManager` вызывает internal listeners перед пользовательским
  `Router`, а raw fallback остается только во внешнем router-е.
- `Client::close()` делегирует в `App::close()`: runtime layer закрывает
  connection и session store как единый lifecycle boundary.
- Saved session continuity must win over fresh config ids: if a loaded session
  has `mtInstanceId`, handshake/login must reuse it and update runtime
  options before `SESSION_INIT`; only sessions without `mtInstanceId` use the
  current `ClientOptions::mtInstanceId`.
- Native Python enums заменить PHP 7.4-compatible constant classes.
- PyMax background telemetry заменить explicit/bounded `TelemetryService`,
  который не влияет на успешность основного API-вызова.
- QR auth polling держать внутри bounded `QrAuthFlow`; WebSocket transport
  добавлять отдельным optional слоем, не смешивая его с TCP auth service.
- Общий runtime читать кадры через `FrameReaderInterface`: TCP использует header
  framing, WebSocket получает уже собранные text messages.
- WebSocket transport валидирует HTTP Upgrade и fail-fast отклоняет
  protocol-invalid server frames: masked server frames, RSV bits без extensions,
  fragmented/oversized control frames, unexpected continuation frames и
  interleaved data frames внутри fragmentation chain. Так как `WsProtocol`
  переносит Max JSON frames, server data frames должны быть text; binary
  messages отклоняются на transport boundary, а собранная text message должна
  быть валидным UTF-8.
- Proxy URL хранить в `ClientOptions` и применять на transport/upload boundary,
  не протаскивая его в services или domain models.
- Session persistence держать за `SessionStoreInterface`: JSON store для
  простого shared hosting, SQLite store как optional backend при наличии
  `pdo_sqlite`, без изменения public client/service API. Store interface
  сохраняет PyMax parity для `save`, `load`, lookup by device/phone,
  token update, delete by token и full local cleanup through
  `deleteAllSessions()`.
- Release packaging держать в `tools/build-release.php` и `just release-zip`,
  не добавляя runtime-зависимость от build tooling.
- `Command::ERROR` превращать в `ApiException`, сохраняя opcode, error code,
  title/message/localizedMessage и raw payload.
- PyMax ping loop держать внутри bounded `Client::runFor()`: отправлять
  `Opcode::PING` с `interactive=true` по `ClientOptions::pingInterval`, без
  отдельного daemon/background loop.
- `relogin()` должен удалять текущую local session из `SessionStoreInterface`,
  сбрасывать in-memory session/login state и опционально очищать config token
  перед повторным `open()`.

## Зоны повышенного риска

- TCP MessagePack/framing/compression.
- MessagePack fallback обязан сохранять 64-bit timestamps/ids; усечение до
  32-bit ломает QR expiry, events и часть protocol payloads.
- Входящий MessagePack декодируется детерминированным PHP-декодером даже при
  установленном `ext-msgpack`: PECL decoder теряет содержимое extension-типов.
  MAX extension code `1` раскрывается как вложенное MessagePack-значение с
  ограничением глубины, а неизвестные extension-типы сохраняются в
  `MessagePackExtension` без потери type/data.
- Auth/login/session token refresh.
- API errors: нельзя терять server error payload или превращать его только в
  generic string exception.
- Upload video/file: HTTP upload, streaming constraints, bounded ожидание
  `NOTIF_ATTACH`, включая события, пришедшие во время HTTP upload до входа в
  blocking wait.
- Event dispatch: raw frames, filters, error scopes, internal-before-user
  handler order.
- Domain bound methods: `Message::answer()`, `Chat::history()` и похожие.
- Telemetry: не допустить скрытого long-running loop или падения основного
  login/runtime из-за диагностического `Opcode::LOG`.
- WebSocket: корректный HTTP Upgrade, Origin `https://web.max.ru`, masked
  client frames, unmasked server frames, ping/pong, strict fragmentation rules
  и bounded message reads.
- Proxy: HTTP CONNECT/SOCKS5 handshake, TLS после CONNECT, учет credentials без
  логирования и одинаковое поведение TCP/WebSocket/upload.
- Persistence: session store не должен терять token refresh или sync markers;
  SQLite schema должна оставаться совместимой с PyMax session columns.
- Contract drift: изменения `src/pymax` в opcodes/commands/event map/payload
  anchors, domain/response/attachment anchors, typed event anchors,
  serialized payload keys, public service methods, public client mixin methods
  или parameter names/order должны сначала пройти `just contract-check`, затем
  получить PHP перенос или осознанное backlog-решение.
