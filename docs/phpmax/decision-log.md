# PHPMax Decision Log

## 2026-07-03 - Integration harness can verify phone auth and session reuse

Решение: `tools/integration-check.php` теперь поддерживает два базовых
real-account входа: token mode через `PHPMAX_TOKEN` и интерактивный phone/SMS
mode через `PHPMAX_AUTH_SMS=1` + `PHPMAX_PHONE`. После успешного TCP login
harness проверяет, что local session сохранена с token/device/mtInstanceId и,
если задан телефон, находится по phone. Затем он открывает второй `Client` с
тем же `workDir/sessionName`, но без token и без SMS auth flow, чтобы доказать
session reuse без повторного запроса кода.

Причина: для проверки “привязывается ли телефон, показывается ли session,
запрашиваются ли коды” нужен управляемый сценарий до внедрения в реальный
проект. Token-only smoke test не доказывает first-login UX и не проверяет,
что сохраненная session сама достаточна для следующего запуска.

## 2026-07-03 - Root README documents PHPMax release usage

Решение: заменить корневой `README.md` с Python/PyMax описания на PHPMax
quick start: требования PHP 7.4+, Composer/release ZIP установка, bounded
`Client::withOpenSession()` сценарий, token login, upload example,
`WebClient`, integration checks и ссылки на `docs/phpmax`.

Причина: `README.md` входит в shared-hosting release ZIP и является публичным
входом для пользователя PHP-библиотеки. Если там остается Python-инструкция,
release artifact вводит в заблуждение и ломает developer experience, даже если
runtime-код и tests уже готовы.

## 2026-07-03 - Direct proxy negative timeouts use a bounded fallback

Решение: `ProxyConnector::connect()` при прямом неположительном timeout
использует `1.0` second вместо `0.001`. Положительные subsecond timeout values
сохраняются и clamp-ятся снизу до `0.001`, как раньше. Loopback HTTP CONNECT
fixture теперь задерживает ответ proxy и запускается с negative timeout, чтобы
проверить, что invalid direct input не превращается в race.

Причина: proxy handshake состоит из connect, request write, response headers и,
для SOCKS5, нескольких round trips. Нормализация `-5.0` в 1 ms формально
защищала stream API от отрицательного значения, но на практике делала
boundary flaky даже на локальном loopback. `1.0` second остается bounded для
shared-hosting runtime и дает понятное поведение при прямом использовании
низкоуровневого adapter-а без `ClientOptions`.

## 2026-07-03 - Extra fields are preserved only for domain models

Решение: `PHPMax\Support\Model` сохраняет unknown fields только для
`PHPMax\Domain\*` subclasses. `PHPMax\Api\*` payload models и
`PHPMax\Session\*` storage models продолжают принимать входной массив через тот
же hydrator, но unknown keys отбрасываются и не попадают в `extra()`/`toArray()`.

Причина: PyMax domain models наследуются от base model with `extra="allow"`,
а API/session models используют стандартное Pydantic поведение и игнорируют
лишние поля. Если PHP payload models сохраняют unknown keys, future или
malformed поля могут случайно уйти обратно в protocol request; для session
storage это еще и риск сохранить отладочные/чужие значения рядом с token state.

## 2026-07-03 - Release preflight validates vendor policy without building ZIP

Решение: добавить `tools/build-release.php --check` и `just release-check`.
`just pre-publish-check` теперь запускает release preflight после PHP gates:
manifest и vendor policy валидируются без создания `dist/phpmax-dev.zip`.
`--dry-run` остается JSON manifest mode, а `--check` является validation mode;
совместное использование этих режимов считается ошибкой конфигурации.

Причина: `--dry-run` удобен для просмотра состава архива, но сам по себе не
является release gate. Если в будущем появятся runtime Composer packages без
собранного `vendor/`, публикация должна падать до release build, а не только
в момент создания ZIP.

## 2026-07-03 - PHP 7.4 compatibility is checked without Composer

Решение: добавить `tools/php74-compat-check.php` и запускать его внутри
`just php-check`. Checker сканирует `src/PHPMax`, `tests-php` и `tools`,
игнорирует строки/docblocks и fail-fast ловит PHP 8+ constructs: native enum,
union types, attributes, constructor property promotion, `match`, nullsafe
operator, PHP 8-only return/property/parameter types и helper calls вроде
`str_contains()`.

Причина: локальный `php -l` проверяет синтаксис текущим установленным PHP.
Если разработчик работает на PHP 8.x, случайное использование PHP 8-синтаксиса
может пройти локально, но сломать shared-hosting PHP 7.4 runtime. Отдельный
lightweight checker сохраняет требование PHP 7.4+ даже без Composer/PHPStan.

## 2026-07-03 - HTTP upload endpoints are restricted to HTTP(S)

Решение: `NativeHttpUploader` принимает только absolute `http`/`https` URL с
host и валидным port `1..65535` для multipart и streaming uploads. `file://`,
`ftp://`, scheme-relative, relative endpoints и port `0` отклоняются как
`UploadException` до запуска cURL или PHP stream fallback.

Причина: upload URL приходит из server payload и находится на security
boundary. cURL и PHP stream wrappers поддерживают больше схем, чем нужно
PHPMax, включая локальные файлы и не-HTTP transport. Fail-fast validation
сохраняет expected HTTP-only contract и не дает broken или malicious endpoint
уйти в сетевой/файловый слой.

## 2026-07-03 - URL source diagnostics redact signed URL secrets

Решение: `File`/`Photo`/`Video` URL-source errors больше не включают полный
URL. В диагностике остается только scheme, host, optional port и marker
`/<redacted>`; query, fragment и userinfo не печатаются.

Причина: upload/download source URLs часто бывают signed URLs с token в query
или credentials в userinfo. Ошибка чтения, HEAD-запроса или HTTP status не
должна превращать такие значения в logs, CLI output или integration failure
messages.

## 2026-07-03 - Upload init responses are semantically validated

Решение: `UploadService` отклоняет video/file init responses до HTTP upload,
если `url` пустой, token пустой или `fileId`/`videoId` не положительный.
Ошибка завершается как `UploadException`, waiter state не создается, HTTP
upload не стартует.

Причина: schema validation гарантирует наличие полей, но не их смысловую
пригодность для streaming upload и ожидания `NOTIF_ATTACH`. Пустой URL, token
или id `0` означает broken server payload; продолжать HTTP upload в таком
состоянии опасно и усложняет диагностику real-account integration checks.

## 2026-07-03 - Transport endpoints fail fast before socket work

Решение: `ClientOptions::host`/`port`, `TcpTransport`,
`WebSocketTransport` parsed URL endpoint и `ProxyConnector` target endpoint
отклоняют empty host и port вне `1..65535` до socket connect, HTTP Upgrade,
HTTP CONNECT или SOCKS5 request.

Причина: bounded shared-hosting runtime должен получать диагностичную ошибку
конфигурации до сетевой операции. Некорректные endpoints не должны превращаться
в неясные PHP stream warnings, зависания connect path или malformed proxy
handshake bytes.

## 2026-07-03 - Runtime and transport timeouts are lower-bound normalized

Решение: `ClientOptions::requestTimeout`, `connectTimeout`,
`uploadHttpTimeout` и `uploadProcessingTimeout` нормализуются до минимума
`0.001`, а `executionSafetyMargin` - до минимума `0.0`. Отрицательные значения
больше не проходят в `App::invoke()`, TCP/WebSocket connect path, upload HTTP
path или расчет execution budget. Direct usage `TcpTransport`,
`WebSocketTransport`, `ProxyConnector` и `NativeHttpUploader` нормализует
timeout на своем boundary, если эти классы используются без `ClientOptions`.

Причина: PHP stream APIs и bounded runtime должны получать предсказуемые
положительные timeouts. `runFor()` уже защищал часть read-timeout paths, но
прямые service calls и connect logic использовали значения из options
напрямую. Дополнительная нормализация на transport/upload boundary защищает
низкоуровневые классы от прямого использования и делает shared-hosting
поведение стабильным для всех public lifecycle сценариев.

## 2026-07-03 - Built-in session stores reject path-like file names

Решение: `JsonFileSessionStore` и `SQLiteSessionStore` принимают только plain
file names для session storage. Empty name, `.`/`..`, `/`, `\` и null byte
отклоняются до создания рабочей директории и до открытия SQLite/JSON файла.

Причина: `ClientOptions::workDir` должен быть единственной границей, внутри
которой built-in stores создают local session files. Path-like `sessionName`
может вывести token/sync storage за пределы ожидаемой директории или случайно
попасть в webroot, что противоречит shared-hosting security model.

## 2026-07-03 - WebSocket runtime accepts only JSON text messages

Решение: `WebSocketTransport` отклоняет binary data frames и валидирует
собранные text messages как UTF-8. Max WebSocket runtime в PHPMax использует
`WsProtocol`, который кодирует и декодирует JSON text frames, поэтому binary
server message или invalid UTF-8 считаются protocol drift и завершаются
`ProtocolException`.

Причина: silent acceptance of binary frames would send arbitrary bytes into the
JSON protocol decoder and hide transport/server contract drift. UTF-8
проверяется только после полной reassembly, чтобы корректно принимать multibyte
символы, разделенные между continuation frames. Fail-fast на transport boundary
делает WebSocket layer предсказуемее для shared-hosting runtime и
parity-тестов.

## 2026-07-03 - Startup failures close runtime resources

Решение: `Client::open()` закрывает `App` runtime, если auth startup или
`onStart` завершается необработанным исключением. Это закрывает transport и
configured session store перед тем, как ошибка вернется пользователю.

Причина: shared-hosting/cron сценарии не должны оставлять открытые TCP
connections, SQLite/file handles или lock-related state после неудачного
старта. Успешно обработанные `onStart` ошибки по-прежнему остаются внутри
router error handling, а необработанные ошибки пробрасываются после cleanup.

## 2026-07-03 - Session stores support full local cleanup

Решение: `SessionStoreInterface` получил `deleteAllSessions()`, а JSON и SQLite
backend-и реализуют полную очистку локальных PHPMax sessions. Метод повторяет
PyMax `SessionStore.delete_all_sessions()` как store-level операцию и не
подменяет серверный `Client::closeAllSessions()`.

Причина: shared-hosting пользователю нужен безопасный способ очистить локальное
хранилище без ручного удаления файлов/SQLite rows. Это также закрывает parity
gap с Python store и сохраняет одинаковое поведение JSON/SQLite backend-ов.

## 2026-07-03 - Integration preflight is secret-free

Решение: `tools/integration-check.php --plan` и `just integration-plan`
показывают список real-account проверок и required env без сетевых запросов,
без требования `PHPMAX_TOKEN` и без вывода значений token/proxy.

Причина: реальные Milestone 6/7 проверки требуют аккаунта, внешней сети и
секретов, поэтому перед запуском нужен безопасный preflight. Он помогает
подготовить env для upload/download/WebSocket/proxy/telemetry сценариев, но не
создает риска случайно залогировать токен или proxy credentials.

## 2026-07-03 - Proxy handshakes are covered by loopback tunnels

Решение: `ProxyConnector` покрыт loopback-тестами для HTTP CONNECT, SOCKS5
без авторизации и SOCKS5 username/password. Тесты проверяют handshake bytes,
`Proxy-Authorization` header, target host/port и то, что после handshake tunnel
реально пропускает данные.

Причина: proxy слой находится на transport/security boundary. URL parsing и
constructor validation недостаточны: ошибка в CONNECT/SOCKS5 framing проявится
только при реальном подключении. Loopback proxy сохраняет deterministic CI gate
без внешнего proxy и без логирования credentials.

## 2026-07-03 - WebSocket transport fails fast on invalid server frames

Решение: `WebSocketTransport` строго валидирует HTTP Upgrade response и
серверные frames. Handshake требует настоящий HTTP `101`, `Upgrade:
websocket`, `Connection: Upgrade` token и корректный `Sec-WebSocket-Accept`.
Серверные frames не могут быть masked, не могут использовать RSV bits без
extensions, control frames не могут быть fragmented или длиннее 125 bytes, а
новый data frame запрещен до завершения текущей fragmentation chain.
Дополнительно введен bounded лимит payload frame-а, чтобы transport не пытался
читать произвольно большой WebSocket message в память.

Причина: WebSocket является optional, но security-sensitive transport boundary.
Мягкое принятие protocol-invalid frames скрывает drift, может нарушить сборку
JSON-сообщений и опасно для shared-hosting runtime, где memory/time budget
должны быть ограничены.

## 2026-07-03 - Saved session mtInstanceId wins during handshake

Решение: если `SessionStoreInterface::loadSession()` возвращает session с
непустым `mtInstanceId`, `Client` синхронизирует этот id в runtime options и
использует его в `SESSION_INIT`. Новый `ClientOptions::mtInstanceId`
используется только для новой session или для старой session без сохраненного
`mtInstanceId`.

Причина: PyMax при старте переиспользует `session_data.mt_instance_id`, если он
есть, и только иначе оставляет текущий config id. Это часть device/session
continuity: случайная замена `mt_instance_id` при каждом запуске с сохраненным
token может ломать login fingerprint и sync continuity.

## 2026-07-03 - Real integration checks stay opt-in

Решение: реальные проверки MAX не входят в `just php-check` и CI. Для них
добавлен отдельный `just integration-check`, который без
`PHPMAX_INTEGRATION=1` безопасно пропускается. При наличии `PHPMAX_TOKEN` он
сначала выполняет preflight без сетевых запросов: валидирует session names,
numeric env parsing/bounds, upload paths, writable workdir,
`Client`/`WebClient` construction и proxy config, не печатая token/proxy
credentials. После preflight harness
проверяет TCP login/profile-state, а дополнительные сценарии включаются
точечными env-параметрами: chat/session reads, bot init data, telemetry login/
navigation, photo/file/video uploads, file/video temporary URL checks,
WebSocket login и proxy path.

Причина: real-account сценарии требуют секретов, могут менять session token,
используют внешнюю сеть и не должны ломать deterministic local/CI gate.
Harness при этом нужен, чтобы закрывать незавершенные Milestone 6/7 проверки
одинаковой воспроизводимой процедурой, не логируя токены и proxy credentials.
Preflight нужен, чтобы malformed env не запускал real network path и не
превращался в частичный integration run.

## 2026-07-03 - Hydrator validates explicit empty and malformed collections

Решение: PHPMax `Model` validates required fields even when input is an
explicit empty array. Explicit malformed `array`, `list<...>` and
`map-list<...>` values throw `ValidationException` instead of being converted
to empty collections. `LoginResponse::contacts` keeps PyMax `list[User | None]`
semantics: `null` items are allowed, scalar items are not. Custom factories for
`Message::attaches` and `MessageDeleteEvent::messageIds` follow the same rule:
explicit malformed attachment items or delete ids fail instead of being kept or
turned into an empty list. List fields also require PHP list-like arrays with
`0..n` integer keys; associative arrays are treated as maps and rejected where
PyMax expects a list, including upload `info` payloads and primitive request
lists such as message ids, user ids, folder ids/include values and filters.
Primitive `list<int>`, `list<string>`, `list<bool>`, `list<array>` and
`list<mixed>` schemas validate every item through the same hydrator path.
Scalar casts are guarded as well: integer-compatible numeric strings/floats
and common boolean strings remain accepted, while malformed scalar payloads
fail instead of using PHP's permissive `(int)`, `(string)` or `(bool)` casts.
`Profile::profileOptions` remains a flexible array/map compatibility exception
because current profile fixtures can arrive as option maps.

Причина: PyMax/Pydantic applies defaults to absent optional fields, but still
fails explicit malformed payload. The previous PHP hydrator blurred this
difference and PHP arrays blur list-vs-map shape unless checked explicitly.
Without this guard, protocol objects could be accepted as lists in login state,
profile options, upload init responses, message lists and event/domain models;
scalar drift like `"abc" -> 0` would be even harder to detect after hydration.

## 2026-07-03 - Auth response models reject empty payloads

Решение: `AuthService` treats `null` and empty response payloads as missing for
all methods that mirror PyMax `require_payload_model`. Empty `AUTH_REQUEST`,
`AUTH`, password, QR, registration and `LOGIN` responses must fail before model
hydration; failed `LOGIN` must not update session state or session store.

Причина: PHP `Model::fromArray([])` applies defaults, while PyMax
`require_payload_model()` rejects falsey payload before Pydantic validation.
Without the guard, auth and login flows could accept an invalid server response
as a default model and then perform session side effects.

## 2026-07-03 - Chat update event mapping is strict for truthy payloads

Решение: known events with falsey payloads keep PyMax raw-frame fallback, но
`CHAT_UPDATE` с непустым payload обязан содержать non-empty nested `chat`
object. Wrapperless chat-like payload, scalar `chat` и пустой nested `chat`
падают до handler dispatch и до raw fallback.

Причина: PyMax `EventMapper` вызывает `Chat.model_validate(frame.payload["chat"])`.
PHPMax ранее принимал wrapperless payload как `Chat` или мог вернуть raw frame
для malformed scalar, что скрывало protocol drift в известном событии.

## 2026-07-03 - Reaction responses keep optional falsey semantics

Решение: empty optional `reactionInfo` возвращает `null`, malformed truthy
`reactionInfo` падает, а `messagesReactions` должен быть map по message id с
валидными reaction payload values.

Причина: PyMax проверяет `if reaction_info` перед `ReactionInfo.model_validate`
для add/remove, а `get_reactions()` ожидает dict и валидирует каждое значение.
PHPMax не должен превращать `{reactionInfo: {}}` в default `ReactionInfo` или
молча игнорировать scalar/map drift.

## 2026-07-03 - User and message list parsing fails fast on malformed items

Решение: `UserService` и `MessageService` сохраняют empty/missing list behavior,
но если response list присутствует и содержит malformed item, PHPMax
выбрасывает ошибку вместо тихого пропуска элемента.

Причина: PyMax `parse_payload_list()` валидирует каждый item через Pydantic.
Тихий skip в PHP давал бы частичный результат для `contacts`, `sessions` или
`messages`, скрывая protocol/model drift от caller-а и от cache layer.

## 2026-07-03 - Chat list parsing fails fast on malformed items

Решение: `ChatService` сохраняет nullable behavior для отсутствующего
`chats` list, но если list присутствует и содержит malformed item, PHPMax
выбрасывает ошибку вместо тихого пропуска элемента.

Причина: PyMax `parse_payload_list()` передает каждый item в Pydantic model
validation. Некорректный элемент списка не должен исчезать незаметно, иначе
cache и caller увидят частичный результат без сигнала о protocol drift.

## 2026-07-03 - Optional empty chat item is missing, not an empty chat

Решение: optional chat response paths используют PyMax-like behavior:
`{chat: {}}` считается отсутствующим `chat` item и возвращает `null` или no-op.
Такой payload не создает `Chat` с пустыми/default полями и не перезаписывает
существующий cached chat.

Причина: PyMax `parse_payload_item_model()` проверяет truthiness item-а перед
model validation. Empty dict в optional path превращается в `None`, а не в
domain object. PHPMax должен сохранять эту разницу между optional parse path и
strict `require_payload_item_model` path.

## 2026-07-03 - Contact update validates response before cache side effects

Решение: `UserService::addContact()` и `removeContact()` проверяют, что
`CONTACT_UPDATE` вернул dict-like payload, до чтения `contact` и до удаления
пользователя из cache. Empty payload остается допустимым для remove, потому что
PHP decode не различает пустой MessagePack map и list.

Причина: PyMax `_contact_action()` вызывает `require_payload_dict()` для add и
remove. Без такого guard-а PHPMax мог удалить пользователя из локального cache
даже если сервер вернул protocol-invalid list-shaped response.

## 2026-07-03 - Photo MIME keeps PyMax source-specific behavior

Решение: для `Photo` из `raw` или `path` PHPMax возвращает MIME как
`image/<extension>`, как PyMax `validate_photo()`. Для URL-источника PHPMax
использует MIME map/guess behavior, поэтому `.jpg` остается `image/jpeg`.

Причина: это значение попадает в multipart `Content-Type` при `PHOTO_UPLOAD`.
Нормализация `.jpg` в `image/jpeg` для всех источников выглядит аккуратнее, но
ломает byte-level/developer parity с PyMax для raw/path upload fixtures.

## 2026-07-03 - Upload waiters track only active ids

Решение: file/video upload waiters хранят ready state только для ids, которые
прямо ожидаются текущим upload-вызовом. `NOTIF_ATTACH` с чужим id проходит
через dispatcher, но не остается в `UploadService`; active expected/ready state
очищается через `finally` после успеха и после HTTP upload failure.

Причина: PyMax хранит futures по активным ids и извлекает их через `pop`.
Если PHPMax будет накапливать все пришедшие attach-события, короткие
shared-hosting запуски могут держать устаревшее состояние между upload
операциями и случайно завершать не тот waiter.

## 2026-07-03 - Required response models fail fast on empty payload

Решение: service methods, которые в PyMax используют `require_payload_model`,
не должны молча гидрировать default PHP model из пустого response payload.
Пустой payload считается ошибкой service boundary до model hydration. Methods,
которые в PyMax используют `parse_payload_model`, сохраняют nullable behavior.

Причина: многие domain models имеют PyMax defaults. Если PHP вызывает
`Model::fromArray([])` без проверки payload, ошибка сервера превращается в
валидный объект с пустыми/default полями. Это уже было возможно для folder
responses, `sendMessage()`/`forwardMessage()`/`editMessage()`/
`readMessage()` state и могло скрыть некорректный `WEB_APP_INIT_DATA`,
upload-init или HTTP upload ответ.

## 2026-07-03 - Payload keys are part of the contract manifest

Решение: `docs/phpmax/contracts.json` хранит не только Python field names для
payload models, но и serialized payload keys после PyMax alias rules.
`just contract-check` через reflection сверяет эти keys с PHP payload schemas.

Причина: protocol drift часто происходит не в имени PHP/Python property, а в
ключе на wire-уровне: `_type`, `mt_instanceid`, `from`, chat option aliases.
Эти расхождения должны падать до публикации так же, как opcode или enum drift.
Глубокие defaults/value fixtures остаются отдельными тестами, потому что
manifest проверяет только top-level key contract.

## 2026-07-03 - Public service methods are contract anchors

Решение: `docs/phpmax/contracts.json` хранит публичные methods из
`src/pymax/api/*/service.py` и ожидаемые PHP method names. `contract-check`
разрешает PHP-only helper methods, но падает, если PyMax public service method
не имеет PHP equivalent.

Причина: пользовательский developer experience ломается не только от opcode или
payload drift, но и от тихо появившегося upstream API action. Surface check
дает ранний сигнал после upstream sync. Для идиоматичных PHP имен используются
явные mappings, например `check_2fa -> checkTwoFactor`.

## 2026-07-03 - Client mixin shortcuts are checked separately

Решение: manifest хранит публичные PyMax methods из `src/pymax/infra/*Mixin`
и проверяет, что они доступны как shortcuts на `PHPMax\Client`.

Причина: service parity не гарантирует developer experience parity. Метод может
быть реализован внутри service, но отсутствовать на публичном `Client`, что
ломает привычный PyMax-style API. Отдельный client surface check ловит такие
расхождения без запрета на PHP-only helpers вроде telemetry shortcuts.

## 2026-07-03 - Core domain models enter contract manifest

Решение: `contracts.json` фиксирует top-level field/key anchors для core
domain models `Chat`, `Message`, `User`, `Profile`, `LoginResponse`, `Name` и
связанных small value objects. `contract-check` сверяет эти anchors с PHP
schemas через reflection.

Причина: service/payload parity недостаточен, если response model теряет поле
после hydration. Первый domain manifest pass сразу выявил drift в `Name`:
PyMax поддерживает `first_name`/`last_name`, а PHP модель их не хранила.
Проверка остаётся top-level; вложенные значения и behavior подтверждаются
focused fixtures.

## 2026-07-03 - Typed event models enter contract manifest

Решение: manifest фиксирует top-level field/key anchors для PyMax typed events:
message delete/read, typing, presence, reaction update и file/video upload
signals. PHP event schemas сверяются через reflection.

Причина: event payload drift ломает dispatcher, пользовательские handlers и
bounded upload wait. Event map уже проверял opcode -> resolver mapping, но не
shape typed event models. Новый слой закрывает этот gap, оставляя behavior
проверку за focused dispatch fixtures.

## 2026-07-03 - User-agent generation mirrors PyMax config anchors

Решение: держать app versions, Android device profiles, locale/timezone anchors
и web header user-agent в `MobileUserAgentPayload`, а `ClientOptions` и
`WebClient` получать готовый payload через existing default methods.

Причина: user-agent участвует в handshake/login fingerprint. Статический
payload повышает риск drift с PyMax и хуже отражает реальное поведение
`ExtraConfig.generate_user_agent()`/`generate_web_user_agent()`. Размещение
генератора в payload-слое сохраняет `ClientOptions` простым и не смешивает
конфигурацию с transport/auth logic.

## 2026-07-03 - Internal dispatch is an App-level typed hook

Решение: перенести PyMax `internal_router` как internal typed listeners в
`App`, вызываемые `ConnectionManager` перед пользовательским event handler.
Internal dispatch не получает raw fallback; raw остается частью внешнего
`Router`.

Причина: PyMax использует internal handlers для service-level runtime hooks,
например upload processing notifications. В PHPMax это нужно для bounded
upload wait: `NOTIF_ATTACH`, пришедший во время HTTP upload до начала blocking
wait, должен быть запомнен и не потерян.

## 2026-07-03 - Staged parity

Решение: проектировать полный parity с PyMax, но реализовывать этапами.

Причина: пакет большой, включает protocol, auth, events, uploads и domain
models. Один большой перенос повышает риск ошибок и затрудняет проверку.

## 2026-07-03 - Shared hosting first

Решение: основной runtime первой версии должен поддерживать cron/short CLI и
bounded execution.

Причина: целевое окружение - shared hosting с ограничениями `max_execution_time`.
Постоянный daemon может быть optional, но не базовым требованием.

## 2026-07-03 - Keep Python reference in repository

Решение: не удалять `src/pymax` на старте PHP-порта.

Причина: нужно регулярно подтягивать upstream PyMax, сравнивать изменения и
принимать решения о переносе в PHPMax.

## 2026-07-03 - TCP first, WebSocket later

Решение: сначала переносить TCP `Client`, затем optional `WebClient`.

Причина: TCP core нужен для основного сценария, а WebSocket/QR добавляет
зависимости и long-running ограничения.

## 2026-07-03 - Schema-driven models

Решение: заменить Pydantic общим PHP hydrator/serializer, а не ручным парсингом
в каждом service.

Причина: PyMax содержит много моделей, aliases, вложенных списков, attachment
discriminators и extra fields. Ручной парсинг быстро станет ненадежным.

## 2026-07-03 - Hydrator accepts PyMax field names

Решение: PHP `Model` принимает на вход и protocol/PHP camelCase keys, и
PyMax/Pydantic snake_case field names. Сериализация остается canonical:
`toArray()` возвращает protocol keys из schema/payload. Special-case wrappers,
например `Message::{message: ...}`, не должны добавлять отсутствующие outer
fields как `null`, чтобы не затирать вложенные значения.

Причина: PyMax `CamelModel` использует aliases и `populate_by_name=True`, из-за
чего реальные fixtures могут приходить как `photoToken`, `photo_token` или
field-name variants. Если поддерживать это точечно в services, parity будет
дрейфовать. Общий hydrator сохраняет единое поведение для domain models,
payloads и attachment discriminator.

## 2026-07-03 - Optional fields preserve PyMax nullability

Решение: optional поля, у которых в PyMax default равен `None`, в PHP-моделях
остаются `null`, если поле отсутствует во входном payload. Пустые коллекции
используются только там, где PyMax задает `default_factory=list/dict` или где
это явно нужно для runtime state.

Причина: искусственная замена `null` на `[]` меняет смысл ответа API и
сериализацию модели. Для `Profile::profileOptions` это особенно важно:
отсутствующие флаги профиля и явно полученный пустой список должны оставаться
различимыми, а проверка 2FA уже умеет безопасно обрабатывать оба случая.

## 2026-07-03 - Attachment models mirror known PyMax types

Решение: каждый известный PyMax attachment `_type` получает concrete PHP model
с явными fields из reference. Attachment discriminator принимает и `_type`, и
`type`. `UnknownAttachment` остается только для будущих или неизвестных типов
и отклоняет известный тип при прямой hydration.

Причина: extra fields полезны для forward compatibility, но они не заменяют
domain parity. Разработчику нужны typed свойства для audio/contact/sticker/
share/call/control/inline keyboard так же, как для photo/video/file. Иначе
часть входящих сообщений формально парсится, но теряет developer experience и
явные payload contracts.

## 2026-07-03 - just as task runner

Решение: использовать `just` как стандартную точку входа для проверок и
pre-publish guard.

Причина: проект будет включать PHP, Python reference, parity fixtures и
документационные проверки. Единый `Justfile` снижает когнитивную нагрузку и
делает workflow одинаковым для агента и разработчика.

## 2026-07-03 - Documentation is maintained with code

Решение: документация обновляется автоматически вместе с кодом. Перед
публикацией всегда проверяется, требует ли изменение исходников обновления docs.

Причина: PHPMax является портом с долгой синхронизацией upstream PyMax. Если
документация отстанет, будущие переносы protocol/auth/session изменений станут
рискованными.

## 2026-07-03 - Lightweight PHP test runner before Composer install

Решение: добавить `tools/run-php-tests.php` и подключить его к `just php-check`.

Причина: в текущем окружении Composer отсутствует, но реализация должна
проверяться уже сейчас. Runner не отменяет PHPUnit, а дает ранний локальный gate
для protocol/model/runtime foundation.

## 2026-07-03 - Message attachments accept prepared payloads first

Решение: на этапе message service принимать attachments как уже подготовленные
payload arrays или `Model` instances, а полноценные file/photo/video upload
helpers перенести отдельным Milestone 6.

Причина: отправка сообщений и upload pipeline имеют разные риски. Message
payload parity можно проверить сейчас через fake transport, а upload требует
HTTP streaming, multipart, ожидания processing notifications и отдельных
bounded event-loop тестов.

## 2026-07-03 - Markdown formatter owns UTF-16 protocol offsets

Решение: держать расчет markdown elements в отдельном `Formatter`, который
возвращает очищенный текст и protocol elements с UTF-16 offsets.

Причина: MAX protocol считает positions в UTF-16 code units. Если считать их
обычной byte/string длиной PHP, emoji и символы за пределами BMP ломают offsets
и форматирование сообщений.

## 2026-07-03 - Typed dispatch before error scopes

Решение: сначала перенести `EventResolver`, `EventMapper`, typed event models и
typed `Router` helpers, а error/disconnect scopes вынести в следующий scoped
шаг.

Причина: resolver/mapper определяет protocol parity для входящих событий и
нужен upload/chat/message runtime. Error scopes меняют политику исполнения
handlers и должны проверяться отдельными тестами, чтобы не смешивать mapping
ошибки с callback error handling.

## 2026-07-03 - Chat cache is service-local for now

Решение: на первом этапе chat service держит cache внутри `ChatService`
instance, без отдельного глобального client state и без domain-bound methods.

Причина: PyMax cache behavior нужен для `get_chats`, но полноценный binding
`Chat::answer()`, `Chat::history()` и shared client state затрагивают messages,
chats и users одновременно. Service-local cache дает полезное поведение сейчас
и оставляет место для отдельного binding слоя.

## 2026-07-03 - Error scopes are synchronous router behavior

Решение: перенести PyMax `global`/`local` error scopes в синхронный PHP
`Router`: handler/filter/start ошибки проходят через `ErrorContext`, а наличие
хотя бы одного подходящего error handler считается обработкой ошибки.

Причина: PHPMax runtime блокирующий и bounded, поэтому async task policy PyMax
не переносится буквально. Но семантика области видимости ошибок важна для
совместимости developer experience и для безопасной обработки user callbacks.

## 2026-07-03 - Disconnect callbacks do not swallow network failures

Решение: `onDisconnect()` callbacks вызываются при нетаймаутных
protocol/network ошибках `runFor()`, ошибки внутри disconnect callbacks
подавляются, но исходная network/protocol ошибка пробрасывается дальше.

Причина: для shared hosting важно явно завершать короткий запуск и не создавать
скрытый reconnect-loop. Callback дает место для логирования/метрик, а решение о
повторном запуске остается внешнему cron/process supervisor слою.

## 2026-07-03 - Domain helpers delegate through binding

Решение: `Message` и `Chat` получают domain-bound helpers только после явной
привязки через `PHPMax\Api\Binding`; сами модели не знают transport/protocol и
делегируют действия в `MessageService`/`ChatService`.

Причина: PyMax developer experience строится вокруг `message.answer()` и
`chat.history()`, но сетевой код нельзя смешивать с моделями. Binding сохраняет
слоистую архитектуру и дает понятную ошибку, если разработчик пытается вызвать
helper на непривязанной модели.

## 2026-07-03 - Upload-backed helper attachments remain prepared payloads

Решение: на этом этапе domain helpers для `answer()`/`reply()`/`edit()`
принимали тот же временный формат attachments, что и `MessageService`:
подготовленные payload arrays или `Model` instances.

Причина: полноценные `Photo`/`File`/`Video` helpers зависят от Milestone 6:
HTTP upload, streaming, processing notifications и bounded event-loop ожидания.
До переноса upload pipeline нельзя обещать file-object attachments.

Статус: временное ограничение снято началом Milestone 6. `MessageService`
теперь принимает `Photo`/`File`/`Video`, а bound helpers используют этот же
путь через service delegation.

## 2026-07-03 - User cache is service-local before shared App state

Решение: `UserService` держит cache пользователей внутри service instance и
выдает bound `User` models; account profile update явно кладет `profile.contact`
в этот cache.

Причина: PyMax хранит пользователей в `app.users`, но PHP runtime пока не
переносит весь stateful App surface. Service-local cache покрывает `getUsers`
cache-miss behavior и не мешает позже добавить общий state, если он понадобится
для sync/login parity.

## 2026-07-03 - Account profile photo object waits for UploadService

Решение: на этом этапе `AccountService::changeProfile()` принимал готовый
`photoToken`, но не принимал объект фото до переноса `UploadService`.

Причина: PyMax умеет загрузить `Photo` внутри `change_profile`, но это зависит
от upload pipeline Milestone 6. Поддержка `photoToken` уже переносит protocol
payload `PROFILE`, а file upload будет добавлен вместе с multipart/streaming
tests.

Статус: временное ограничение снято началом Milestone 6.
`AccountService::changeProfile()` теперь принимает `Photo` object и загружает
его через `UploadService` с `profile=true`.

## 2026-07-03 - UploadService owns HTTP upload boundary

Решение: `Photo`/`File`/`Video` являются file value objects, а весь TCP/HTTP
upload orchestration живет в `PHPMax\Api\Uploads\UploadService`. HTTP отправка
изолирована за `HttpUploaderInterface`.

Причина: upload pipeline пересекает TCP opcodes, HTTP multipart/streaming и
event processing. Если смешать это с `MessageService` или domain models,
появится хрупкая зависимость между сетевым кодом, serialization и public
helpers. Отдельный service позволяет подменять HTTP слой в тестах и позже
добавить более строгий streaming adapter без изменения message/account API.

## 2026-07-03 - File/video uploads wait for NOTIF_ATTACH with a deadline

Решение: после HTTP upload для file/video PHPMax ждет matching `NOTIF_ATTACH`
только до `ClientOptions::uploadProcessingTimeout`. Frames, которые не относятся
к текущему upload, передаются в обычный event handler через `ConnectionManager`.

Причина: PyMax ждет processing notification через async future, но PHPMax
ориентирован на bounded CLI/cron runs. Таймаут предотвращает зависание процесса,
а forwarding нерелевантных событий сохраняет поведение dispatcher во время
upload wait.

## 2026-07-03 - String defaults are data, not callbacks

Решение: `Model` вызывает default только если это callable object/closure, а не
любую строку, для которой `is_callable()` вернул true.

Причина: PHP считает строки вроде `FILE` callable из-за одноименной функции
`file()`. Для enum-like constants и `_type` defaults это приводило бы к
скрытым runtime-вызовам вместо сохранения protocol value.

## 2026-07-03 - Download helpers return temporary URL models

Решение: `MessageService::getFileById()` и `getVideoById()` переносят PyMax
behavior: через TCP запрашивают temporary URL и возвращают `FileRequest` или
`VideoRequest`. Они не скачивают файл и не открывают HTTP stream сами.

Причина: в PyMax эти методы являются protocol helpers для `FILE_DOWNLOAD` и
`VIDEO_PLAY`, а не download manager. Разделение сохраняет слоистость:
message service отвечает за payload parity, а реальную загрузку/проигрывание
потребитель делает отдельно по временной ссылке. Для `VideoRequest` сохранена
нормализация dynamic URL key в поле `url`.

## 2026-07-03 - Heartbeat ping is bounded by runFor

Решение: PyMax `_ping_loop` перенесен не как отдельный background loop, а как
deadline внутри `Client::runFor()`. PHPMax отправляет `Opcode::PING` с
`interactive=true` по `ClientOptions::pingInterval`; `0.0` отключает heartbeat.

Причина: PHPMax ориентирован на shared hosting и короткие CLI/cron запуски.
Отдельный бесконечный ping loop нарушал бы bounded runtime, а deadline внутри
`runFor()` сохраняет protocol parity без daemon-поведения.

## 2026-07-03 - File/video HTTP upload streams directly from chunks

Решение: `NativeHttpUploader::uploadStream()` больше не собирает весь
file/video upload body во временный `php://temp` stream. При наличии `ext-curl`
он передает cURL `CURLOPT_READFUNCTION`, который читает данные из `StreamBody`
поверх chunk iterator. Реальный HTTP path использует cURL upload mode с
`CURLOPT_CUSTOMREQUEST=POST`, потому что PHP cURL не предоставляет
`CURLOPT_POSTFIELDSIZE`; body streaming закреплен loopback-тестом, который
проверяет метод, `Content-Length` и полученные bytes.
Photo multipart path также закреплен loopback-тестом: проверяются cURL POST,
динамический boundary, `Content-Length`, тело файла и sanitizing field/filename
headers без CRLF injection.

Причина: целевое окружение включает shared hosting и большие вложения. Даже
`php://temp` может уходить в диск или память и скрывать фактическую стоимость
upload. `StreamBody` держит только небольшой buffer текущего чтения, проверяет
`Content-Length` и fail-fast сигнализирует, если источник отдал меньше или
больше байт, чем было заявлено.

## 2026-07-03 - URL file sources reject non-2xx HTTP responses

Решение: `BaseFile` проверяет HTTP status для URL-источников в `read()`,
`size()` и `iterChunks()`. `read()` и `iterChunks()` используют GET, `size()`
использует HEAD; non-2xx status приводит к `UploadException` даже если сервер
вернул body или `Content-Length`.

Причина: PyMax вызывает `resp.raise_for_status()` при чтении URL и при
chunk-iteration. PHP stream wrappers и `get_headers()` могут вернуть headers
404/500 с `Content-Length`, поэтому без явной проверки `size()` принимал
ошибочную страницу как размер файла и мог запустить некорректный upload.

## 2026-07-03 - URL file sources are HTTP(S)-only

Решение: `File::fromUrl()`, `Photo::fromUrl()` и `Video::fromUrl()` принимают
только absolute `http`/`https` URL с host. `file://`, `php://`, `ftp://`,
relative и hostless URL отклоняются на value-object boundary.

Причина: PyMax URL-источники проходят через `aiohttp.ClientSession`, то есть
предназначены для HTTP(S). В PHP stream wrappers иначе могли бы открыть
локальные файлы или служебные stream schemes через параметр `url`, обходя явный
`path` source и ухудшая security posture shared-hosting runtime.

## 2026-07-03 - Empty raw sources are not readable upload content

Решение: `BaseFile::read()` и `size()` для raw source с пустой строкой
выбрасывают `UploadException`. Constructor остается permissive, чтобы object
shape совпадал с PyMax, но фактическое чтение/размер пустого raw считается
ошибкой. `iterChunks()` для пустого raw остается empty iterator, как PyMax
`iter_chunks()`.

Причина: PyMax проверяет `if self.raw`, а не `raw is not None`, поэтому
`read()`/`size()` для `raw=b""` не возвращают валидные bytes/zero size. В PHP
проверка `raw !== null` принимала пустую строку и могла довести empty photo до
HTTP multipart upload.

## 2026-07-03 - Reconnect is bounded by runFor budget

Решение: `ClientOptions::reconnect` по умолчанию включен, как в PyMax
`ExtraConfig`, а `ClientOptions::reconnectDelay` по умолчанию равен `1.0`.
В PHPMax reconnect выполняется только внутри `Client::runFor()` и только пока
остается execution budget текущего короткого запуска.

Причина: PyMax `start()` рассчитан на async long-running loop и может
переподключаться бесконечно. PHPMax должен работать на shared hosting, поэтому
переносим developer experience (`onDisconnect($exception, true, $delay)` и
повторный `onStart`) без превращения CLI/cron запуска в скрытый daemon. Если
`reconnect=false`, сохраняется прежнее fail-fast поведение: connection закрыт,
disconnect callback получает `false, 0.0`, а исходная ошибка пробрасывается.

## 2026-07-03 - Bot init data is a TCP service helper

Решение: `BotsService::getInitData()` переносит PyMax `WEB_APP_INIT_DATA`
как обычный TCP service helper и возвращает `InitData`, а не открывает web app
и не зависит от будущего `WebClient`.

Причина: PyMax bot init data находится в API service layer, а WebSocket/QR
остается отдельным optional layer. Разделение позволяет использовать bot web
app URL в TCP core уже сейчас и не тянуть browser/websocket runtime в базовый
Composer-пакет.

## 2026-07-03 - Telemetry is explicit and bounded

Решение: перенести telemetry как `TelemetryService` с явной отправкой
`Opcode::LOG`, payload builder parity и опциональным login event после
успешной авторизации при `ClientOptions::telemetry=true`. PyMax background
telemetry loop не переносится буквально.

Причина: в PyMax telemetry работает как async background service, что естественно
для long-running runtime. PHPMax должен быть совместим с shared hosting и
короткими CLI/cron запусками, поэтому diagnostics не должны создавать скрытый
daemon, удерживать соединение или ломать login/API flow при ошибке отправки.

## 2026-07-03 - MessagePack fallback preserves 64-bit integers

Решение: pure-PHP `MsgpackPayloadCodec` кодирует и декодирует `uint64`/`int64`,
а значения за пределами PHP integer range отклоняет диагностичной
`ProtocolException`.

Причина: MAX payloads содержат millisecond timestamps и ids, которые выходят за
32-bit range. Усечение до 32-bit уже ломало QR expiry во fake-transport тесте и
могло бы тихо портить protocol parity в runtime.

## 2026-07-03 - QR auth belongs to auth layer before WebClient

Решение: перенести QR auth opcodes (`GET_QR`, `GET_QR_STATUS`, `LOGIN_BY_QR`,
`AUTH_QR_APPROVE`) в `AuthService` и добавить bounded `QrAuthFlow` до
реализации WebSocket `WebClient`.

Причина: QR authorization contract не зависит от конкретного WebSocket
transport и нужен будущему `WebClient`, но может быть проверен сейчас через
TCP/fake-transport. Polling ограничен server expiry и `qrPollTimeout`, чтобы
не создавать скрытый long-running процесс на shared hosting.

## 2026-07-03 - ConnectionManager reads through protocol-specific readers

Решение: обобщить runtime через `FrameProtocolInterface` и
`FrameReaderInterface`. TCP остается default path с `TcpFrameReader`, а
WebSocket использует `WebSocketFrameReader`, который получает целые text
messages от message transport.

Причина: TCP читает фиксированный binary header и MessagePack payload, а
WebSocket уже предоставляет JSON message frames. Смешивать эти правила в одном
`ConnectionManager::readFrame()` нельзя: это сделало бы transport слой хрупким
и мешало бы сохранить TCP как стабильный baseline.

## 2026-07-03 - WebClient reuses bounded Client lifecycle

Решение: `WebClient` является thin public subclass поверх общего `Client`
lifecycle: web user-agent, `WsProtocol`, `WebSocketTransport`,
`WebSocketFrameReader` и `QrAuthFlow` по умолчанию.

Причина: PyMax `WebClient` отличается transport/auth setup, но high-level API,
router и service facade остаются теми же. Повторное использование bounded
runtime сохраняет shared-hosting ограничения и уменьшает риск расхождения TCP и
WebSocket developer experience.

## 2026-07-03 - 2FA management stays inside AuthService

Решение: перенести PyMax 2FA-management (`set_2fa`, `remove_2fa`,
`change_password`, `check_2fa`) как методы `AuthService` и тонкие public
shortcuts на `Client`. Payload contracts живут в `Api\Auth`; email-код
запрашивается через `EmailCodeProviderInterface`.

Причина: это security-sensitive auth surface. Пароли, email-коды и track ids не
должны попадать в domain models, logs или runtime helpers. Порядок
`expectedCapabilities` сохранен как в PyMax: сначала password, затем hint,
затем email, даже если email validation выполняется до hint validation.

## 2026-07-03 - Proxy is a transport/upload boundary concern

Решение: `ClientOptions::proxy` применяется только на границах
`TcpTransport`, `WebSocketTransport` и `NativeHttpUploader`. Services, domain
models и payload contracts о proxy не знают. Поддержаны HTTP CONNECT и SOCKS5;
unsupported schemes завершаются fail-fast. HTTP uploads через proxy требуют
`ext-curl`, чтобы stream fallback не обошел proxy незаметно.

Причина: PyMax использует один `ExtraConfig.proxy` для TCP, WebSocket и upload
HTTP requests. В PHPMax это должно оставаться инфраструктурной настройкой,
иначе proxy начнет протекать в API surface и усложнит тестирование payload
parity. Credentials считаются чувствительными и не должны логироваться.

## 2026-07-03 - SQLite session store is optional parity backend

Решение: сохранить `JsonFileSessionStore` как простой default для shared
hosting и добавить `SQLiteSessionStore` как optional adapter за тем же
`SessionStoreInterface`. SQLite schema использует PyMax-compatible columns:
token, device_id, phone, mt_instance_id и отдельные sync markers.

Причина: PyMax использует SQLite session store, поэтому PHPMax должен уметь
сохранять те же данные без потери token refresh и sync state. Но требовать
`pdo_sqlite` для всей библиотеки нельзя: на shared hosting JSON-файл остается
самым переносимым вариантом. Optional adapter дает parity путь для окружений,
где SQLite доступен, не меняя public client/service API.

## 2026-07-03 - Telemetry navigation planner is explicit and bounded

Решение: перенести PyMax `NavigationPlanner`, screen graph и route profiles,
но использовать их только для явной сборки короткого NAV/PERF batch через
`TelemetryService::plannedNavigationEvents()` или
`sendPlannedNavigation()`. Public client shortcut:
`Client::sendTelemetryNavigationSession()`.

Причина: PyMax telemetry loop рассчитан на async long-running app и делает
sleep между событиями. Для PHPMax на shared hosting скрытый background loop
недопустим. При этом сам planner важен для payload parity: screen ids,
action ids, CHAT/CHATS source params и render events должны строиться
единообразно и тестируемо.

## 2026-07-03 - Contract manifest is a pre-publish gate

Решение: добавить `docs/phpmax/contracts.json`, который генерируется из
локального `src/pymax` reference и проверяется командой
`php tools/contract-manifest.php check`. `just php-check` и
`just pre-publish-check` запускают эту проверку автоматически.

Причина: PHPMax переносит внутренний protocol/domain surface PyMax, где drift
одного opcode, command, event mapping или enum value может сломать runtime без
очевидной ошибки компиляции. Manifest фиксирует reference anchors для commands,
opcodes, event types, dispatch event map, domain enum values, API enum values,
response payload key values и payload model fields; PHP constants и
EventResolver сверяются с ним до публикации.

## 2026-07-03 - Release ZIP targets shared hosting runtime

Решение: добавить `tools/build-release.php` и `just release-zip`. Архив
содержит runtime PHPMax (`src/PHPMax`), `composer.json`, `LICENSE`/`README.md`,
docs/phpmax, optional `vendor` и сгенерированный fallback `autoload.php`.
Python reference `src/pymax`, tests и tooling в архив не включаются.

Причина: целевой сценарий включает shared hosting без shell Composer install.
Пока runtime Composer packages отсутствуют, архив может работать без `vendor`.
Если такие packages появятся, builder должен fail-fast требовать заранее
собранный `vendor/`, чтобы пользователь получил готовый ZIP, а не неполную
поставку.

## 2026-07-03 - API error frames keep payload shape

Решение: `App::invoke()` преобразует `Command::ERROR` в `ApiException`.
Exception хранит opcode, server error code, title, message,
localizedMessage и raw payload. Если payload не проходит `MaxApiError`
hydration, используется fallback `unknown_error`, но исходный payload все
равно сохраняется.

Причина: PyMax возвращает структурированный `ApiError`, а PHPMax не должен
терять server error details в generic строке. Это важно для auth, uploads,
rate-limit и user-facing diagnostics, где код ошибки и localized message
нужны вызывающему коду.

## 2026-07-03 - Login response becomes client state

Решение: после успешного login `Client` хранит bound `LoginResponse` и отдает
PyMax BaseClient-like state через `me()`, `chats()`, `contacts()` и
`messages()`. Чаты из login response добавляются в `ChatService` cache, а
profile/contact users - в `UserService` cache. `relogin()` удаляет текущую
local session, сбрасывает in-memory state и может очистить config token.

Причина: в PyMax login/sync response является рабочим состоянием клиента, а не
только ответом auth service. Если не связать эти модели с services, domain
helpers на объектах из login state будут падать, а повторные `getChat()`/
`getUser()` будут делать лишние запросы вместо использования уже полученного
sync state.

## 2026-07-03 - App owns runtime state caches

Решение: `App` хранит runtime state `me`, `chats`, `users`, `contacts` и
`messages`, а chat/user services читают и обновляют этот общий state. `Client`
public accessors только отдают данные из `App`, а `relogin()` очищает `App`
state вместе с текущей session.

Причина: в PyMax caches живут на уровне `App`, поэтому login/sync response,
service fetches и domain helpers видят один источник данных. Service-local
caches в PHPMax могли бы разойтись с login state и будущим sync/event state,
особенно после `leaveGroup()`, `deleteChat()` или `removeContact()`.

## 2026-07-03 - App close owns runtime cleanup

Решение: `Client::close()` делегирует в `App::close()`, а `App::close()`
закрывает и `ConnectionManager`, и `SessionStoreInterface`.

Причина: PyMax закрывает transport и session store вместе в `App.close()`.
Для PHPMax это особенно важно с optional SQLite store: короткие CLI/cron
запуски и `withOpenSession()` не должны оставлять открытые file/SQLite handles.

## 2026-07-03 - Profile update writes App state

Решение: `AccountService::changeProfile()` обновляет профиль через
`App::setProfile()`. Это одновременно меняет `App::me()` и кладет contact в
общий user cache.

Причина: PyMax после `change_profile` записывает новый профиль в `app.me` и
обновляет `app.users`. PHPMax public `Client::me()` читает данные из `App`,
поэтому profile update не должен оставаться только локальным состоянием
`AccountService`.

## 2026-07-03 - Error scope validation fails fast

Решение: `Router::onError()` и `Client::onError()` бросают
`InvalidArgumentException`, если scope не равен `global` или `local`.

Причина: PyMax валидирует `ErrorScope(scope)` и не превращает неизвестное
значение в global handler. Для PHPMax silent fallback опасен: опечатка в
локальном error handler-е могла расширить область обработки ошибок на весь
router tree и скрыть реальные runtime сбои.
