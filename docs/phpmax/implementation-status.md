# Implementation Status

Дата: 2026-07-03

## Реализовано сейчас

- Composer skeleton:
  - `composer.json` с PHP 7.4 platform config;
  - PSR-4 namespace `PHPMax\\`;
  - корневой `README.md` переписан как публичный PHPMax quick start и входит
    в release ZIP;
  - `phpunit.xml`, `phpstan.neon`;
  - lightweight runner `tools/run-php-tests.php` для окружений без Composer;
  - `tools/php74-compat-check.php` для проверки PHP 8+ syntax/API drift даже
    на машинах, где локальный `php -l` запускается новой версией PHP.
- `just` workflow:
  - `just php-check` выполняет PHP lint, PHP 7.4 compatibility scan,
    contract check и тесты;
  - `just contract-check` сверяет `docs/phpmax/contracts.json` с Python
    reference, PHP constants/event resolver, PHP payload/domain/event schema
    keys и API service/client method surface;
  - `just release-check` валидирует release manifest и vendor policy без
    создания ZIP;
  - `just release-zip` собирает shared-hosting runtime archive;
  - `just integration-check` запускает opt-in real-account smoke checks для
    TCP login, optional WebSocket, uploads, download URLs, bot init data,
    telemetry, chat/session reads и proxy propagation;
  - `just integration-plan` показывает безопасный план real-account проверок
    и нужные env-переменные без сетевых запросов и без вывода секретов;
  - `just pre-publish-check` проверяет agents/docs/PHP/release gates;
  - `docs-guard` учитывает PHP source, tooling и GitHub workflow changes.
- GitHub Actions:
  - `tests.yml` запускает PHPMax `just pre-publish-check` на PHP 7.4 и 8.3;
  - Python reference lint/test baseline сохранен отдельными jobs;
  - `publish.yml` собирает PHPMax release ZIP вместо старой PyPI-публикации;
  - PR template ориентирован на PHPMax gates, docs review и parity fixtures.
- Core foundation:
  - exceptions;
  - contract manifest из Python reference: commands, opcodes, event types,
    dispatch event map, domain enum values, API enum values и payload model
    anchors with serialized payload key metadata, domain/response/attachment
    model anchors, typed event model anchors, plus public API service method
    mappings/parameter mappings and public client mixin shortcut
    mappings/parameter mappings;
  - PHP 7.4-compatible constants для `Command`, `Opcode`, базовых domain enum
    values, включая `AccessType`;
  - PHP 7.4-compatible API constant classes для auth/chat/message/session/user
    enums и response payload key anchors;
  - chat/message/account/user services читают response payloads через PyMax-like
    payload key constant classes вместо scattered string literals;
  - schema-driven `Model` с aliases, defaults, nested models и extra fields;
  - `Model` принимает protocol camelCase aliases и PyMax/Pydantic snake_case
    field names, а сериализует обратно в canonical protocol keys;
  - unknown fields сохраняются только для `PHPMax\Domain\*` моделей, как в
    PyMax domain `extra="allow"`; API payload и session storage models
    игнорируют unknown keys и не сериализуют их обратно;
  - `Model` валидирует required fields даже при explicit empty input and fails
    explicit malformed array/list/map-list values instead of silently
    converting them to empty collections;
  - `list<...>` fields and list-like custom factories reject associative arrays
    where PyMax expects list payloads;
  - primitive list schemas validate item values too: `list<int>` keeps
    Pydantic-compatible integer coercion, `list<string>` rejects non-strings,
    and `list<mixed>` still enforces PHP list shape;
  - scalar casting is guarded: PHPMax keeps Pydantic-compatible int/bool
    coercion but rejects malformed scalar payloads instead of relying on PHP
    `(int)`/`(string)`/`(bool)` casts;
  - custom model factories follow the same fail-fast rule for message
    attachments, login contacts and message delete ids;
  - optional model fields сохраняют PyMax nullability: отсутствующий
    `Profile::profileOptions` остается `null`, а не превращается в пустой
    список; explicit `profileOptions` payload remains a flexible array/map
    compatibility exception because current server fixtures may be option maps;
  - первые domain models: sync/session/profile/user/chat/member/message/
    elements/reactions/attachments/presence/events/folders/account sessions.
  - known attachment models покрывают PyMax fields для photo/video/file/audio/
    contact/sticker/control/inline keyboard/share/call; unknown attachments
    сохраняют future fields, но не принимают известный `_type`; attachment
    discriminator принимает и `_type`, и `type`.
  - `TranscriptionStatus` добавлен как PHP 7.4-compatible constant class для
    audio attachments.
- Protocol foundation:
  - TCP frame models;
  - `TcpPacketFramer`;
  - pure-PHP MessagePack codec для используемых типов, включая uint64/int64
    timestamps и signed offsets;
  - `FrameProtocolInterface` и `FrameReaderInterface` для TCP/WebSocket runtime;
  - `WsProtocol` для JSON WebSocket frames с protocol version 11;
  - TCP payload decoder с key normalization;
  - LZ4 block decompression;
  - optional Zstd adapter через `ext-zstd`.
- Runtime foundation:
  - blocking `TcpTransport`;
  - blocking `WebSocketTransport` со strict HTTP Upgrade validation, masked
    client text frames, unmasked server frame guard, ping/pong, close handling,
    fragmentation validation и bounded message reader;
  - `ProxyConfig`/`ProxyConnector` для HTTP CONNECT и SOCKS5 proxy на
    TCP/WebSocket transport boundary;
  - `ConnectionManager` с seq wrap, request/response matching и raw event dispatch;
  - `ConnectionManager` event listeners для internal runtime hooks перед
    пользовательским event handler;
  - `App::invoke()`;
  - `App::close()` как общий lifecycle close boundary для connection и session
    store;
  - `App` internal typed event router для service-level hooks без raw fallback;
  - `App` runtime state для `me`, `chats`, `users`, `contacts`, `messages`,
    включая profile update через account service;
  - `ApiException` для `Command::ERROR` frames с сохранением opcode,
    error/title/message/localizedMessage и raw payload;
  - `ClientOptions`, `Client`, `Router`, bounded lifecycle anchors;
  - `ClientOptions` нормализует runtime/connect timeouts и execution safety
    margin, чтобы отрицательные значения не попадали в stream calls и не
    расширяли execution budget;
  - direct transport/upload boundaries (`TcpTransport`, `WebSocketTransport`,
    `ProxyConnector`, `NativeHttpUploader`) тоже нормализуют отрицательные
    timeout до безопасного lower bound при использовании без `ClientOptions`;
  - прямой `ProxyConnector::connect()` для неположительного timeout использует
    bounded fallback `1.0` second, чтобы HTTP CONNECT/SOCKS5 handshake не
    становился flaky 1 ms race при invalid direct input;
  - `ClientOptions`, `TcpTransport`, `WebSocketTransport` и `ProxyConnector`
    fail fast на empty host и port вне `1..65535`;
  - `ClientOptions` default user-agent использует PyMax-like random Android
    anchors: app versions, device profiles, locale/timezone и build numbers;
  - `Client::me()`, `chats()`, `contacts()`, `messages()`, `stop()` и
    `relogin()` для PyMax BaseClient-like public state/reset surface;
  - bounded heartbeat `Opcode::PING` внутри `Client::runFor()` по
    `ClientOptions::pingInterval`;
  - disconnect callbacks для нетаймаутных protocol/network ошибок в `runFor()`;
  - bounded reconnect policy в `runFor()` с `reconnect`/`reconnectDelay`.
- Auth/session services:
  - mobile/web handshake payloads;
  - token login;
  - SMS auth flow, 2FA password challenge и registration confirm;
  - auth response models that mirror PyMax `require_payload_model` reject empty
    payloads before hydration and before session-store side effects;
  - 2FA account management: create auth track, validate password, email code,
    hint, set/remove/change password и profile option check;
  - public `Client::setTwoFactor()`, `removeTwoFactor()`,
    `changePassword()`, `checkTwoFactor()`;
  - QR auth contracts: request/check/confirm QR и approve QR login;
  - bounded `QrAuthFlow` с `QrHandlerInterface` и `ConsoleQrHandler`;
  - `Client::authorizeQrLogin()` shortcut;
  - token refresh с обновлением session store;
  - PyMax-like login sync persistence: login response `time` обновляет все
    sync markers, `config.hash` обновляет config hash, отсутствующие значения
    сохраняют предыдущий state, а `mtInstanceId` пишется в session store;
  - saved session continuity: если loaded session содержит `mtInstanceId`,
    handshake переиспользует его вместо нового random config id как PyMax;
  - login response связывается с service helpers и наполняет общий `App`
    chat/user runtime cache;
  - profile update обновляет `App::me()` и общий user cache.
- WebClient foundation:
  - `WebClient` scaffold поверх общего `Client` lifecycle;
  - PyMax-like random web user-agent default (`DeviceType::WEB`) и
    `WebHandshakePayload`;
  - `QrAuthFlow` по умолчанию, если пользователь не передал custom auth flow;
  - явно заданный custom auth flow и уже web-compatible user-agent не
    перезатираются constructor-ом `WebClient`;
  - `ClientOptions::wsUrl` для WebSocket endpoint.
- Proxy foundation:
  - `ClientOptions::proxy`;
  - TCP и WebSocket transports подключаются через proxy при заданном URL;
  - `NativeHttpUploader` применяет proxy для cURL uploads;
  - uploads через proxy требуют `ext-curl`, чтобы proxy не был молча обойден
    stream fallback-ом;
  - unsupported proxy schemes fail fast.
- Release ZIP foundation:
  - `tools/build-release.php` с `--dry-run` и `--output`;
  - `just release-zip`;
  - fallback `autoload.php` генерируется внутрь архива для окружений без
    Composer;
  - архив включает `src/PHPMax`, `docs/phpmax`, `composer.json`,
    `LICENSE`/`README.md` и optional `vendor`;
  - Python reference, tests и tooling в runtime archive не попадают;
  - если появятся runtime Composer packages, build потребует существующий
    `vendor/`.
- Real integration harness:
  - `tools/integration-check.php`, `just integration-plan` и
    `just integration-check`;
  - `--plan`/`PHPMAX_INTEGRATION_PLAN=1` печатает список real-account проверок
    и состояние required env без сетевых запросов;
  - без `PHPMAX_INTEGRATION=1` безопасно пропускается и не делает сетевых
    запросов;
  - при `PHPMAX_INTEGRATION=1` выполняется secret-safe preflight до сети:
    session names, numeric env parsing/bounds, readable upload paths, writable
    workdir, `Client`/`WebClient` construction и proxy config проверяются до
    первого connect;
  - при `PHPMAX_TOKEN` выполняет TCP login/profile-state smoke check;
  - при `PHPMAX_AUTH_SMS=1` и `PHPMAX_PHONE` выполняет interactive phone/SMS
    auth flow через console code prompt, затем проверяет сохраненную local
    session и повторный login из этой session без token/SMS auth flow;
  - optional env flags подключают fetch chats/sessions, bot init data,
    telemetry login/navigation, photo/file/video uploads, file/video
    temporary URL checks, WebSocket login и proxy path;
  - session data пишется в `PHPMAX_WORKDIR` или temp workdir, токены/proxy
    credentials в вывод не попадают даже при preflight errors.
- Message service foundation:
  - send/forward/get/edit/history/delete;
  - pin;
  - file/video temporary URL requests by attachment id;
  - response edge parity: `sendMessage()`/`forwardMessage()`/`readMessage()`
    require payload like PyMax `require_payload_model`, `editMessage()`
    requires the `message` item like `require_payload_item_model`, while
    file/video URL helpers return `null` for empty payloads like
    `parse_payload_model`;
  - malformed `messages` list items in get/history responses fail fast like
    PyMax `parse_payload_list` instead of being silently skipped;
  - reactions add/get/remove;
  - reaction response edge parity: empty optional `reactionInfo` returns
    `null`, malformed reaction payloads fail fast, and `messagesReactions`
    must stay a message-id keyed map;
  - read mark;
  - public shortcuts `Client::sendMessage()`, `forwardMessage()`,
    `getMessages()`, `getMessage()`, `editMessage()`, `fetchHistory()`,
    `deleteMessage()`, `pinMessage()`, `addReaction()`, `getReactions()`,
    `removeReaction()`, `readMessage()`, `getFileById()`,
    `getVideoById()`.
- Domain binding:
  - `PHPMax\Api\Binding` привязывает `Message`, `Chat`, `User` и
    `MessageDeleteEvent` к services;
  - `Message::reply()`, `answer()`, `forward()`, `pin()`, `edit()`,
    `delete()`, `read()`, `react()`, `unreact()`, `getReactions()`;
  - `Chat::answer()`, `history()`, `getMessage()`, `getMessages()`,
    `leave()`, `delete()`, `invite()`, `removeUsers()`, `pinMessage()`,
    `updateSettings()`, `reworkInviteLink()`;
  - `User::addContact()`, `removeContact()`, `getChatId()`;
  - `MessageDeleteEvent` хранит привязку к `MessageService` как в PyMax;
  - mapped events и service responses возвращают bound domain models.
- Event dispatch foundation:
  - `EventResolver` и `EventMapper` для message/chat/delete/read/typing/
    presence/reactions/attach events;
  - typed `Router::onMessage()`/`onTyping()`/`onChatUpdate()` helpers;
  - `Router::onError()` с `global`/`local` scopes, `ErrorContext` и
    fail-fast validation неизвестного scope;
  - `Router::onDisconnect()` и `Client::emitDisconnect()`;
  - raw fallback после typed dispatch;
  - mapper edge parity: falsey payloads for known events keep PyMax raw-frame
    fallback, but truthy `CHAT_UPDATE` must contain a non-empty nested `chat`
    object and malformed payload fails before raw fallback;
  - internal typed dispatch перед user router/raw fallback;
  - runtime path для events, пришедших между request и response.
- Chat service foundation:
  - create group, invite/remove users, settings/profile update;
  - join/resolve/rework invite link;
  - get/fetch chats с service-local cache;
  - PyMax edge parity для chat links и pagination marker: invalid group links
    fail fast, `joinChannel()` accepts non-join channel links, and
    `fetchChats(0)` sends current timestamp marker like PyMax `marker or now`;
  - optional empty `chat` response items mirror PyMax
    `parse_payload_item_model`: they return `null`/no-op and do not overwrite
    cached chats;
  - malformed `chats` list items now fail fast like PyMax
    `parse_payload_list` instead of being silently skipped;
  - join request confirm/decline/fetch;
  - leave/delete chat;
  - public `Client` shortcuts для перенесенных chat methods.
- User/account service foundation:
  - fetch/get/search users with service-local cache;
  - add/remove/import contacts and local dialog chat id calculation;
  - contact add/remove responses mirror PyMax `_contact_action`
    `require_payload_dict`: invalid list-shaped `CONTACT_UPDATE` payloads
    fail before cache side effects;
  - malformed `contacts` and `sessions` list items fail fast like PyMax
    `parse_payload_list`;
  - sessions list;
  - account profile update by prepared `photoToken` or uploaded `Photo`;
  - profile photo upload URL request;
  - create/get/update/delete folders;
  - folder responses now mirror PyMax `require_payload_model`: empty folder
    create/list/update/delete payloads fail fast instead of returning default
    models;
  - close all other sessions with token update;
  - logout;
  - public `Client` shortcuts для перенесенных user/account methods.
- Bot service foundation:
  - `BotsService::getInitData()` для `WEB_APP_INIT_DATA`;
  - `InitData` domain model;
  - empty bot init data responses fail fast before `InitData` hydration;
  - public `Client::getBotInitData()` shortcut.
- Telemetry foundation:
  - `TelemetryEvent`/`TelemetryPayload` models;
  - `TelemetryPayloadBuilder` для login/navigation/open-chat/open-chats
    payload parity;
  - `TelemetryService::sendEvents()` через `Opcode::LOG`;
  - `Screen`/`RouteProfile`/`NavigationRules`/`NavigationPlanner` для PyMax-like
    navigation route planning без background loop;
  - public `Client::sendTelemetryEvents()` и `sendTelemetryLogin()`;
  - public `Client::sendTelemetryNavigationSession()` для явной bounded
    отправки planned NAV/PERF batch;
  - `ClientOptions::telemetry=false` по умолчанию и bounded login telemetry
    после успешной авторизации только при явном включении;
  - ошибки telemetry не пробрасываются за service boundary.
- Upload service foundation:
  - `PHPMax\Files\File`, `Photo`, `Video` с `path`/`url`/`raw` источниками,
    размером и chunk iteration;
  - file source helpers покрыты fixtures для raw/path/url name inference,
    local read/size/chunk iteration, PyMax-like photo raw/path MIME и photo
    URL extension validation;
  - `UploadService` для `PHOTO_UPLOAD`, `VIDEO_UPLOAD`, `FILE_UPLOAD`;
  - photo multipart upload и `_type` attachment payloads;
  - photo HTTP upload response validation mirrors PyMax: invalid JSON,
    missing `photos` map and missing token for requested `photo_id` fail as
    `UploadException`;
  - file/video chunk upload через `HttpUploaderInterface`;
  - empty, missing-info, malformed and semantically invalid video/file upload
    init payloads fail before HTTP upload starts, including empty upload URL,
    empty token and non-positive file/video ids;
  - direct cURL streaming через `StreamBody` без предварительного
    `php://temp` накопления всего тела запроса;
  - native HTTP uploader принимает только absolute `http`/`https` upload URLs
    с host и валидным port; он отклоняет `file://`/`ftp://`/relative
    endpoints и port `0` до запуска cURL/stream клиента;
  - bounded ожидание `NOTIF_ATTACH` для file/video processing, включая
    attach-события, пришедшие во время HTTP upload до wait loop;
  - upload waiters хранят состояние только для активных ожидаемых
    file/video ids, игнорируют чужие `NOTIF_ATTACH` и очищают active state
    после успеха или HTTP upload failure;
  - `FileRequest`/`VideoRequest` response models для download/playback URL,
    включая dynamic video URL key normalization;
  - `MessageService` принимает `Photo`/`File`/`Video` objects в attachments;
  - `Client::uploadPhoto()`, `uploadVideo()`, `uploadFile()` возвращают typed
    attach payload objects, не plain arrays;
  - bound `Message::answer()` и `Chat::answer()` проверены с upload-backed
    photo/video/file attachments.
- Formatting:
  - markdown formatter для headings, quotes, links и inline markers;
  - UTF-16 offsets для protocol elements, включая emoji/surrogate pairs;
  - formatter parity fixtures покрывают все inline marker types, multiline
    code block language skip, invalid/multiline markers, nested close order,
    links и offsets после emoji.
- Persistence:
  - `SessionStoreInterface`;
  - `JsonFileSessionStore` с atomic write и file lock;
  - optional `SQLiteSessionStore` с PyMax-compatible session columns, indexes
    by `device_id`/`phone`, token update, sync marker persistence и
    `deleteAllSessions()` full local cleanup;
  - built-in JSON/SQLite stores fail fast на path-like или empty
    `sessionName`, чтобы session storage не уходил за пределы `workDir`.

## Проверено

Текущий локальный gate:

```bash
just pre-publish-check
```

Результат на 2026-07-03:

```text
AGENTS.md and GEMINI.md are identical.
Source and docs changes are both present.
PHP 7.4 compatibility check passed.
Contract manifest is in sync.
Composer is not installed; skipping composer validate.
.............................
Assertions: 1303
OK
Release spec is valid.
```

## Важно: еще не готово

- Auth/login/handshake flow покрыт fake-transport тестами, но реальные
  интеграционные проверки с аккаунтом не запускались.
- API services пользователей/account/folders подключены к public client и
  покрыты fake-transport тестами.
- Event mapper/resolver покрывает основные TCP events, internal-before-user
  dispatch, raw fallback и error/disconnect scopes.
- API error handling покрыт runtime тестами: valid error payload превращается
  в `ApiException`, malformed payload получает fallback `unknown_error`, raw
  payload сохраняется.
- Message service и public `Client` shortcuts покрыты fake-transport тестами
  для send/forward/get/edit/history/delete/pin/reactions/read/download helper
  flow.
- Contract manifest покрывает 4 commands, 164 opcodes, 13 event types,
  9 dispatch map entries, 5 domain enum groups/25 values, 16 API enum
  groups/47 values, 71 payload model anchors with field/key metadata,
  46 domain/response/attachment model anchors with field/key metadata,
  7 typed event model anchors with field/key metadata, 8 API service
  domains/77 public method mappings/77 public parameter mappings plus
  59 public client mixin method mappings/59 public parameter mappings;
  `just php-check` теперь падает, если JSON manifest устарел, PHP
  constants/event resolver разошлись с PyMax reference, PHP payload/domain/
  event schemas сериализуют другой набор top-level keys, public PyMax service
  method не имеет PHP equivalent, PyMax client mixin method не доступен на
  `Client`, или public method parameters разошлись по имени/порядку.
- Domain model audit восстановил `Name::firstName`/`lastName` и снял
  ошибочную обязательность `Name::name`/`type`, чтобы PHP model соответствовал
  PyMax `Name`.
- Domain/response manifest audit закрепил auth response aliases
  `LOGIN`/`REGISTER`, snake_case sync storage keys и attachment/download
  model keys; лишний canonical `VideoAttachment::url` убран в пользу отдельной
  `VideoRequest`.
- Runtime reconnect policy покрыта fake-transport тестом: disconnect callback,
  повторный `onStart` и обработка события после reconnect.
- Runtime heartbeat покрыт fake-transport тестом: idle `runFor()` отправляет
  `Opcode::PING` с `interactive=true`, а `pingInterval=0.0` отключает ping.
- Lifecycle close покрыт fake-store тестами: `close()`, `withOpenSession()`,
  `relogin(false)` и unhandled startup failure закрывают configured session
  store через `App::close()`.
- Public client state/relogin покрыт fake-transport тестом: login profile/chats/
  contacts/messages доступны через `Client`, login models bound к services,
  chat/user caches seeded, `relogin(false)` удаляет текущий session token и
  сбрасывает in-memory login state.
- Auth login sync persistence покрыта fake-store тестами: все четыре
  sync markers, config hash и `mtInstanceId` сохраняются как в PyMax
  `_update_session`, а отсутствующие `time`/`config.hash` оставляют прежний
  sync state.
- Saved session continuity покрыта fake-transport тестом: handshake использует
  stored `mtInstanceId`/`deviceId` из session store, а token-auth без saved
  session использует fresh config ids.
- `App` runtime state покрыт fake-transport тестами: chat/user services пишут
  в общий cache, `leaveGroup()`/`deleteChat()`/`removeContact()` очищают тот же
  state, а `Client` accessors читают данные из `App`.
- Profile runtime state покрыт fake-transport тестом: `changeProfile()`
  обновляет `AccountService::profile()`, `App::me()` и общий user cache.
- Chat service покрыт fake-transport тестами, но реальные интеграционные
  проверки еще не запускались.
- User/account/folders/bots покрыты fake-transport тестами, но реальные
  интеграционные проверки еще не запускались.
- Telemetry покрыта fake-transport тестами для payload builder, `Opcode::LOG`,
  no-op empty batch, swallow-failure behavior, автоматического login event при
  `ClientOptions::telemetry=true`, navigation planner transitions, CHAT/CHATS
  source params и planned NAV/PERF batch отправки. PyMax background telemetry
  loop не переносится буквально.
- QR auth покрыт fake-transport тестами для payload/opcode parity и bounded
  `QrAuthFlow` immediate confirmation. Реальная QR/WebClient авторизация еще
  не проверялась.
- 2FA management покрыт fake-transport тестами для `AUTH_CREATE_TRACK`,
  `AUTH_VALIDATE_PASSWORD`, `AUTH_VERIFY_EMAIL`, `AUTH_CHECK_EMAIL`,
  `AUTH_VALIDATE_HINT`, `AUTH_CHECK_PASSWORD`, `AUTH_SET_2FA`, порядка
  `expectedCapabilities` и public `Client::checkTwoFactor()`.
- WebSocket foundation покрыт fake message-transport и byte-level transport
  тестами: `WsProtocol` JSON encode/decode, event forwarding before matching
  response, `App::invoke()` protocol version 11, `WebClient` default/preserved
  web user-agent и QR/custom auth flow, strict handshake validation, masked server frame guard,
  control frame limits, RSV rejection, fragmentation reassembly and invalid
  continuation/data interleaving failures, а также rejection binary data frames
  на JSON text transport boundary. Собранные text messages валидируются как
  UTF-8 после fragment reassembly. Реальный WebSocket handshake с Max еще не
  проверялся.
- Proxy foundation покрыт unit-тестами для URL parsing, default ports,
  credentials, stream/cURL normalization и fail-fast validation в
  TCP/WebSocket/uploader constructors. `ProxyConnector` дополнительно покрыт
  loopback-тестами HTTP CONNECT, SOCKS5 без auth и SOCKS5 username/password,
  включая проверку tunnel bytes после handshake. HTTP CONNECT fixture покрывает
  задержанный response при direct negative timeout, чтобы bounded fallback не
  регрессировал в flaky 1 ms read window. Реальные внешние proxy подключения
  еще не запускались.
- Release ZIP builder покрыт тестом dry-run manifest и реальной сборки через
  `ext-zip` или системный `zip`, если backend доступен. Проверяется наличие
  fallback `autoload.php`, `src/PHPMax`, docs/phpmax и отсутствие `src/pymax`/
  tests в архиве; распакованный archive smoke-test проверяет, что fallback
  autoload реально загружает public clients, transport classes, JSON session
  store и file/photo helpers без Composer. Корневой `README.md` дополнительно
  проверяется как PHPMax-документация в source tree и extracted archive.
- JSON/SQLite session stores покрыты unit-тестом сохранения/загрузки, поиска
  по device/phone, token refresh, sync markers, reconnect-after-close,
  удаления одной session, full local cleanup через `deleteAllSessions()` и
  fail-fast отклонения небезопасных session file names.
  В окружениях без `pdo_sqlite` тест проверяет диагностичную ошибку optional
  adapter-а.
- Upload service покрыт fake-transport/fake-HTTP тестами для обычного
  processing wait и pre-ready attach во время HTTP upload, но реальные
  интеграционные проверки upload endpoints еще не запускались.
- `NativeHttpUploader` для file/video streaming требует `ext-curl`; без него
  photo multipart fallback работает через PHP streams, а file/video streaming
  завершается диагностичной `UploadException`. С `ext-curl` тело file/video
  upload читается напрямую из chunk iterator через `StreamBody`, без
  предварительного накопления всего файла во временный stream; реальные cURL
  multipart photo и streaming file/video POST paths закреплены loopback-тестами.
  Non-HTTP(S), scheme-relative и relative upload endpoints отклоняются до
  запуска HTTP client path.
- URL-источники `File`/`Photo`/`Video` теперь проверяют HTTP status для
  `read()`/`size()`/`iterChunks()` и fail-fast отклоняют non-2xx responses как
  PyMax `raise_for_status()`. URL source принимает только absolute `http` или
  `https` URL с host и валидным port, чтобы PHP stream wrappers не читали
  локальные/служебные схемы или не стартовали с broken endpoint; diagnostic
  errors redact query, fragment and userinfo; success/error/scheme/port paths
  покрыты loopback и unit-тестами.
- Empty raw sources now fail on `read()`/`size()` like PyMax falsey raw
  handling; empty raw photo upload stops before HTTP multipart.
- Domain-bound helpers используют тот же `MessageService`, поэтому
  upload-backed attachments доступны через bound `Message::answer()` и
  `Chat::answer()`; photo/video/file paths покрыты fake-transport/fake-HTTP
  fixtures, включая bounded `NOTIF_ATTACH` wait для video/file.
- Download helpers для `get_file_by_id`/`get_video_by_id` перенесены и покрыты
  fake-transport тестами, но реальные temporary URL ответы Max еще не
  проверялись.
- WebSocket `WebClient` scaffold добавлен, но real account QR/WebSocket
  integration еще не запускалась.
- `just integration-check` добавлен как opt-in вход для реальных проверок, но
  в текущем окружении он был выполнен только в disabled/skip, safe plan,
  enabled-without-token fail-fast и SMS-missing-phone preflight режимах,
  потому что реальный `PHPMAX_TOKEN`/телефонный код не предоставлен.
- Composer validate/PHPUnit/PHPStan не запускались, потому что Composer не
  установлен в текущем окружении.

## Следующий технический шаг

Продолжать Milestone 6/7:

1. Проверить real account upload integration: photo, file, video.
2. Проверить real account download/playback integration для file/video
   temporary URLs.
3. Проверить real account bot init data integration.
4. Запустить real account telemetry checks через `just integration-check` для
   login и planned navigation batches, если telemetry нужна в production
   сценарии.
5. Проверить real account WebClient QR login и bounded `runFor()` поверх
   WebSocket transport.
6. Проверить real proxy integration для TCP, WebSocket и uploads.
7. После Milestone 6 расширить real integration и long-run lifecycle проверки.
