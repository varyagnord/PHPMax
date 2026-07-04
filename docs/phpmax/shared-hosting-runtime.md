# Shared Hosting Runtime

## Основная модель запуска

PHPMax должен работать в окружениях, где нельзя держать постоянный daemon.
Основной сценарий первой версии:

- cron запускает PHP CLI script;
- script открывает соединение;
- выполняет bounded work;
- сохраняет session/sync;
- закрывает соединение до `max_execution_time`.

Session persistence может быть JSON-файлом или SQLite DB. Оба варианта должны
лежать вне webroot; SQLite требует `pdo_sqlite` и полезен, когда нужно хранить
несколько sessions с быстрым поиском по device/phone.

Для shared hosting без shell Composer install использовать release ZIP:
`just release-zip`. Архив содержит fallback `autoload.php`, runtime
`src/PHPMax`, docs/phpmax и optional `vendor`, если он был собран заранее.

## Public lifecycle anchors

- `Client::open()` - открыть transport, handshake, auth/login.
- `Client::close()` - через `App::close()` закрыть transport и session store.
- `Client::runFor(int $seconds)` - слушать события и ping не дольше заданного
  лимита.
- `Client::withOpenSession(callable $callback)` - короткий сценарий:
  открыть, выполнить действие, закрыть.
- `ClientOptions::reconnect` и `reconnectDelay` - best-effort reconnect после
  нетаймаутных protocol/network ошибок внутри `runFor()`.
- `ClientOptions::pingInterval` - интервал heartbeat `Opcode::PING` внутри
  `runFor()`; default `30.0`, значение `0.0` отключает heartbeat.

## Execution budget

- Всегда учитывать `ini_get('max_execution_time')`.
- Если лимит известен, оставлять safety margin минимум 2-5 секунд.
- Любой blocking read/write должен иметь timeout.
- `ClientOptions::requestTimeout`, `connectTimeout`, `uploadHttpTimeout`,
  `uploadProcessingTimeout` и `executionSafetyMargin` нормализуются до
  безопасных нижних границ, чтобы отрицательные пользовательские значения не
  попадали в stream/runtime calls.
- Direct usage `TcpTransport`, `WebSocketTransport`, `ProxyConnector` и
  `NativeHttpUploader` также нормализует timeout на boundary, если эти классы
  используются без `ClientOptions`. `ProxyConnector` при прямом неположительном
  timeout использует bounded fallback `1.0` second, чтобы invalid user input не
  превращался в случайный 1 ms handshake race.
- `ClientOptions::host`, `port`, transport endpoints и proxy target endpoints
  должны fail-fast отклонять empty host и port вне `1..65535` до socket
  connect или handshake.
- Reconnect не должен превращаться в бесконечный daemon: попытки восстановления
  выполняются только пока остается execution budget текущего `runFor()`.
- Любой public close path (`close()`, `stop()`, `withOpenSession()`,
  `relogin()`) должен закрывать session store, чтобы SQLite/file handles не
  оставались открытыми после короткого CLI/cron запуска.
- Если startup падает во время auth или `onStart`, `Client::open()` должен
  закрыть transport и session store перед возвратом ошибки.
- `SessionStoreInterface::deleteAllSessions()` должен очищать только локальное
  хранилище PHPMax; серверные сессии закрываются отдельным API методом
  `Client::closeAllSessions()`.
- Heartbeat ping не должен запускать отдельный background loop: ping отправляется
  только внутри текущего bounded `runFor()` и использует timeout, ограниченный
  оставшимся execution budget.
- QR auth polling не должен быть бесконечным. `QrAuthFlow` ограничен
  `ClientOptions::qrPollTimeout` и временем истечения QR, которое возвращает
  сервер.
- Upload/file processing wait не должен бесконечно ждать событие.
- File/video upload processing ждать только через bounded deadline
  `uploadProcessingTimeout`; нерелевантные TCP events должны уходить дальше в
  event handler, а не теряться внутри upload wait.
- Telemetry не должна запускать скрытый daemon/background loop. В PHPMax она
  отправляется явно или один раз после login при `ClientOptions::telemetry=true`
  и не должна ломать основной сценарий при ошибке `Opcode::LOG`.
- Telemetry navigation planner не делает `sleep` и не запускает цикл. Он
  собирает короткий NAV/PERF batch в памяти и отправляет его только по явному
  вызову.
- WebSocket `WebClient` использует тот же bounded lifecycle: `open()`,
  `runFor($seconds)`, `close()` и reconnect только в рамках execution budget.
  Он не должен работать как бесконечный daemon в HTTP-request lifecycle.
- Proxy handshake должен уважать connect/read timeouts и fail-fast завершаться
  понятной ошибкой. Proxy credentials не логировать и не включать в сообщения
  исключений. HTTP CONNECT/SOCKS5 handshake должен оставаться покрытым
  loopback-тестами без внешнего proxy.
- Для больших file/video uploads preferred runtime имеет `ext-curl`. Если его
  нет, PHPMax должен завершаться понятной `UploadException`, а не читать
  большой файл целиком в строку.
- При наличии `ext-curl` file/video uploads должны идти через direct read
  callback (`StreamBody`), который держит только текущий buffer, а не
  предварительно собирает весь upload body в `php://temp`. Реальный cURL path
  должен оставаться streaming POST и покрываться loopback-тестом.
- Photo multipart uploads могут читать photo body целиком, как PyMax, но
  multipart headers должны экранировать field/filename values и покрываться
  loopback-тестом, чтобы не допустить CRLF injection.
- HTTP upload endpoints, которые приходят от сервера для photo/file/video,
  должны быть absolute `http`/`https` URL с host. `NativeHttpUploader`
  отклоняет `file://`, `ftp://`, scheme-relative, relative URL и port вне
  `1..65535` до запуска cURL или PHP stream fallback.
- URL-источники вложений должны проверять HTTP status перед использованием
  body или `Content-Length`; non-2xx response считается ошибкой upload source.
  Допустимы только absolute `http`/`https` URL с host, чтобы `url` не мог
  открыть локальные файлы или служебные PHP stream wrappers. Port должен быть
  валидным TCP port `1..65535`.
- Ошибки URL-источников не должны печатать query, fragment или userinfo:
  signed URL tokens считаются секретами.
- Empty raw sources считаются ошибкой при `read()`/`size()` и не должны
  доходить до HTTP upload.

## Что не обещаем в v1

- Строгий realtime на shared hosting.
- Постоянное WebSocket-соединение в HTTP-request lifecycle.
- Фоновые задачи после завершения PHP-процесса.
- Автоматическую установку Composer dependencies на сервере без shell-доступа:
  такие зависимости должны быть включены в release ZIP заранее.

## Безопасность

- Session-файлы и SQLite DB хранить вне webroot или явно предупреждать
  пользователя.
- `ClientOptions::sessionName` и custom file names для built-in JSON/SQLite
  stores должны быть plain file names. Path segments, `.`/`..` и null bytes
  отклоняются до создания директории или открытия storage.
- Atomic write + lock при сохранении JSON session; SQLite store использует
  транзакционные записи PDO SQLite и file permissions `0600`.
- Не логировать token, phone полностью, SMS-коды, 2FA password.
- Не логировать proxy credentials.
- Ошибки доступа к session-store должны быть диагностичными.
