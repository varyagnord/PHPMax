# PHPMax

PHPMax is a PHP 7.4+ SDK port of PyMax for the unofficial Max internal API.
The goal is behavioral parity with PyMax while keeping the runtime practical
for shared hosting, cron jobs and short CLI scripts.

> [!WARNING]
> PHPMax uses an unofficial internal Max API. The API can change without
> notice, and using this library can violate service terms. Use it at your own
> risk.

## Status

This repository is an active PHP port. The Python implementation in `src/pymax`
is kept as the reference implementation for contract checks and upstream sync.

Implemented foundations include:

- PHP 7.4-compatible Composer package with PSR-4 namespace `PHPMax\\`;
- TCP client runtime, bounded lifecycle, reconnect and heartbeat;
- JSON and optional SQLite session stores;
- auth/session flows, token login, SMS auth, 2FA helpers and QR auth
  foundation;
- messages, chats, users, account/folders, bots and domain-bound helpers;
- typed events, router, raw fallback, error scopes and disconnect callbacks;
- file/photo/video upload foundations with bounded processing waits;
- WebSocket `WebClient` scaffold, proxy adapters and explicit telemetry;
- release ZIP workflow for shared hosting without shell Composer install.

Real-account integration checks are intentionally opt-in and require your own
token/account configuration.

## Session Storage

`JsonFileSessionStore` keeps the historical multi-session behavior by default.
Applications that own exactly one account session, such as a cron-driven
personal messenger integration, can enable the explicit single-session mode:

```php
use PHPMax\Session\JsonFileSessionStore;

$store = new JsonFileSessionStore(
    __DIR__ . '/var/phpmax',
    'personal-account.json',
    true
);
```

In this mode every successful `saveSession()` atomically replaces the previous
entry. When a legacy file contains several entries, `loadSession()` preserves
the first one because older PHPMax versions treated it as active. Alternate
device and phone lookups are restricted to that same entry, and the next save
compacts the file to one session. Cross-process lifecycle generation and
application-level reconnect locking remain the responsibility of the host
application.

## Requirements

- PHP 7.4 or newer;
- `ext-json`;
- `ext-openssl`;
- optional `ext-curl` for streaming file/video uploads and uploads through a
  proxy;
- optional `ext-pdo_sqlite` for SQLite session storage;
- optional `ext-zstd` for Zstd-compressed TCP payloads.

## Installation

When the package is installed through Composer:

```bash
composer require varyagnord/phpmax
```

Until the package is published on Packagist, add this repository as a VCS
source first:

```bash
composer config repositories.phpmax vcs https://github.com/varyagnord/PHPMax
composer require varyagnord/phpmax:dev-main
```

For shared hosting without shell access, build a runtime archive locally and
upload the ZIP contents:

```bash
just release-zip
```

The release archive contains `autoload.php`, `src/PHPMax`, `docs/phpmax`,
`composer.json` and optional `vendor/` if runtime Composer packages are added
later.

## Quick Start

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Domain\Message;

$client = new Client(new ClientOptions([
    'phone' => '+79990000000',
    'workDir' => __DIR__ . '/var/phpmax',
    'sessionName' => 'main.json',
]));

$client->onStart(static function (Client $client): void {
    $profile = $client->me();
    $userId = $profile !== null && $profile->contact !== null
        ? $profile->contact->id
        : null;

    echo 'Client started, user id: ' . ($userId !== null ? $userId : 'unknown') . PHP_EOL;
});

$client->onMessage(static function (Message $message, Client $client): void {
    echo $message->chatId . ': ' . $message->text . PHP_EOL;

    if ($message->text === '/start') {
        $message->reply('PHPMax is running');
    }
});

$client->withOpenSession(static function (Client $client): void {
    $client->runFor(25);
});
```

`Client::withOpenSession()` is the preferred short-script shape: it opens the
connection, runs bounded work and always closes transport/session resources.

## Token Login

If you already have a valid token:

```php
<?php

use PHPMax\Client;
use PHPMax\Config\ClientOptions;

$client = new Client(new ClientOptions([
    'token' => getenv('PHPMAX_TOKEN') ?: null,
    'workDir' => __DIR__ . '/var/phpmax',
    'sessionName' => 'token-session.json',
]));

$client->withOpenSession(static function (Client $client): void {
    $client->sendMessage(123456789, 'Hello from PHPMax');
});
```

Do not log tokens. Keep `workDir` and session files outside the webroot.

## Sending Files

```php
<?php

use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Files\Photo;

$client = new Client(new ClientOptions([
    'token' => getenv('PHPMAX_TOKEN') ?: null,
    'workDir' => __DIR__ . '/var/phpmax',
]));

$client->withOpenSession(static function (Client $client): void {
    $photo = Photo::fromPath(__DIR__ . '/avatar.png');
    $attachment = $client->uploadPhoto($photo);

    $client->sendMessage(123456789, 'Photo uploaded', null, [$attachment]);
});
```

For large file/video uploads, install `ext-curl`; PHPMax streams those uploads
through a read callback instead of buffering the entire request body.

## WebClient and QR Auth

`WebClient` uses the shared bounded lifecycle, WebSocket transport and QR auth
flow:

```php
<?php

use PHPMax\Config\ClientOptions;
use PHPMax\WebClient;

$client = new WebClient(new ClientOptions([
    'workDir' => __DIR__ . '/var/phpmax',
    'sessionName' => 'web.json',
]));

$client->withOpenSession(static function (WebClient $client): void {
    $client->runFor(25);
});
```

The default QR handler prints the QR payload in CLI. Production code can pass a
custom `QrHandlerInterface` implementation to `WebClient`.

## Integration Checks

Local deterministic gates:

```bash
just doctor
just php-check
just pre-publish-check
```

Real-account checks are disabled by default:

```bash
just integration-plan
PHPMAX_INTEGRATION=1 PHPMAX_TOKEN=... just integration-check
```

To verify first login by phone, SMS-code prompting, local session persistence
and login reuse from the saved session:

```bash
PHPMAX_INTEGRATION=1 PHPMAX_AUTH_SMS=1 PHPMAX_PHONE=+79990000000 just integration-check
```

Optional environment flags enable upload/download/WebSocket/proxy/telemetry
paths. The integration harness performs a secret-safe preflight and does not
print token or proxy credentials.

## Documentation

PHPMax-specific documentation lives in `docs/phpmax`:

- `docs/phpmax/README.md` - documentation hub;
- `docs/phpmax/architecture.md` - architecture and layer boundaries;
- `docs/phpmax/roadmap.md` - staged parity plan;
- `docs/phpmax/upstream-sync.md` - mandatory PyMax upstream sync log;
- `docs/phpmax/testing.md` - test and parity strategy;
- `docs/phpmax/implementation-status.md` - current implementation status.

The original PyMax RST documentation remains as reference material for the
Python package and parity work.

## Development

```bash
just list
just contract-check
just php-check
just release-check
just pre-publish-check
```

Before publishing to git, source changes must be reviewed together with
documentation changes. If a source change is cosmetic and does not require docs,
run `DOCS_REVIEWED=1 just docs-guard` after that decision is made.

## License

MIT. See `LICENSE`.
