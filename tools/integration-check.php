<?php

declare(strict_types=1);

require __DIR__ . '/../tests-php/bootstrap.php';

use PHPMax\Client;
use PHPMax\Auth\ConsoleSmsCodeProvider;
use PHPMax\Auth\SmsAuthFlow;
use PHPMax\Config\ClientOptions;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Session\JsonFileSessionStore;
use PHPMax\WebClient;

final class PHPMaxIntegrationSkip extends RuntimeException
{
}

final class PHPMaxIntegrationRunner
{
    /** @var int */
    private $passed = 0;
    /** @var int */
    private $skipped = 0;
    /** @var int */
    private $failed = 0;
    /** @var string */
    private $workDir = '';
    /** @var array<string, mixed> */
    private $clientOptions = [];

    public function run(): int
    {
        if ($this->shouldPrintPlan()) {
            $this->printPlan();
            return 0;
        }

        if (!$this->flag('PHPMAX_INTEGRATION')) {
            $this->out('SKIP integration checks: set PHPMAX_INTEGRATION=1 and PHPMAX_TOKEN to run real MAX checks.');
            return 0;
        }

        $token = $this->env('PHPMAX_TOKEN');
        if ($token === null && !$this->flag('PHPMAX_AUTH_SMS')) {
            $this->err('FAIL integration checks: PHPMAX_TOKEN is required when PHPMAX_INTEGRATION=1 unless PHPMAX_AUTH_SMS=1 is set.');
            return 2;
        }

        try {
            $this->prepareEnabledRun($token);
        } catch (Throwable $e) {
            $this->err('FAIL integration preflight: ' . get_class($e) . ': ' . $this->redactSecrets($e->getMessage()));

            return 2;
        }

        $this->out('PHPMax integration checks are enabled.');
        $this->out('Session workdir: ' . $this->workDir);
        if ($this->env('PHPMAX_PROXY') !== null) {
            $this->out('Proxy: configured');
        }

        $this->check('tcp-login', function (): void {
            $this->withClient($this->clientOptions, function (Client $client): void {
                if ($client->loginResponse() === null) {
                    throw new PHPMaxException('Login response was not populated');
                }
                if ($client->me() === null) {
                    throw new PHPMaxException('Profile state was not populated');
                }
            });
        });

        $this->check('local-session-stored', function (): void {
            $sessionName = $this->env('PHPMAX_SESSION_NAME') ?: 'integration-session.json';
            $store = new JsonFileSessionStore($this->workDir, $sessionName);
            try {
                $session = $store->loadSession();
                if ($session === null) {
                    throw new PHPMaxException('No local session was saved');
                }
                if ($session->token === '' || $session->deviceId === '' || $session->mtInstanceId === null || $session->mtInstanceId === '') {
                    throw new PHPMaxException('Saved local session is incomplete');
                }
                $phone = $this->env('PHPMAX_PHONE');
                if ($phone !== null && $store->loadSessionByPhone($phone) === null) {
                    throw new PHPMaxException('Saved local session is not indexed by configured phone');
                }
            } finally {
                $store->close();
            }
        });

        $this->check('saved-session-login', function (): void {
            $sessionOptions = $this->buildSavedSessionOptions($this->env('PHPMAX_SESSION_NAME') ?: 'integration-session.json');
            $this->withClient($sessionOptions, function (Client $client): void {
                if ($client->loginResponse() === null || $client->me() === null) {
                    throw new PHPMaxException('Saved-session login did not populate login/profile state');
                }
            });
        });

        $this->check('fetch-chats', function (): void {
            if (!$this->flag('PHPMAX_FETCH_CHATS')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_FETCH_CHATS=1 to run real chat-list read check');
            }
            $this->withClient($this->clientOptions, function (Client $client): void {
                $client->fetchChats();
            });
        });

        $this->check('sessions-list', function (): void {
            if (!$this->flag('PHPMAX_FETCH_SESSIONS')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_FETCH_SESSIONS=1 to run real sessions read check');
            }
            $this->withClient($this->clientOptions, function (Client $client): void {
                $client->getSessions();
            });
        });

        $this->check('telemetry-login', function (): void {
            if (!$this->flag('PHPMAX_TELEMETRY_LOGIN')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_TELEMETRY_LOGIN=1 to run real telemetry login check');
            }
            $this->withClient($this->clientOptions, function (Client $client): void {
                if (!$client->sendTelemetryLogin()) {
                    throw new PHPMaxException('Telemetry login event was not accepted');
                }
            });
        });

        $this->check('telemetry-navigation', function (): void {
            if (!$this->flag('PHPMAX_TELEMETRY_NAVIGATION')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_TELEMETRY_NAVIGATION=1 to run real telemetry navigation check');
            }
            $this->withClient($this->clientOptions, function (Client $client): void {
                if (!$client->sendTelemetryNavigationSession()) {
                    throw new PHPMaxException('Telemetry navigation batch was not accepted');
                }
            });
        });

        $this->check('bot-init-data', function (): void {
            $botId = $this->envInt('PHPMAX_BOT_ID');
            if ($botId === null) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_BOT_ID to run bot init data check');
            }
            $chatId = $this->envInt('PHPMAX_BOT_CHAT_ID');
            $startParam = $this->env('PHPMAX_BOT_START_PARAM');
            $this->withClient($this->clientOptions, function (Client $client) use ($botId, $chatId, $startParam): void {
                $initData = $client->getBotInitData($botId, $chatId, $startParam);
                if ($initData->queryId === null || $initData->queryId === '' || $initData->url === null || $initData->url === '') {
                    throw new PHPMaxException('Bot init data response is incomplete');
                }
            });
        });

        $this->check('photo-upload', function (): void {
            if (!$this->flag('PHPMAX_UPLOAD_PHOTO')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_UPLOAD_PHOTO=1 to run real photo upload check');
            }
            $photo = $this->photoFixture();
            $this->withClient($this->clientOptions, function (Client $client) use ($photo): void {
                $payload = $client->uploadPhoto($photo);
                if ($payload->photoToken === null || $payload->photoToken === '') {
                    throw new PHPMaxException('Photo upload did not return a token');
                }
            });
        });

        $this->check('file-upload', function (): void {
            if (!$this->flag('PHPMAX_UPLOAD_FILE')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_UPLOAD_FILE=1 to run real file upload check');
            }
            $file = $this->fileFixture();
            $this->withClient($this->clientOptions, function (Client $client) use ($file): void {
                $payload = $client->uploadFile($file);
                if ($payload->fileId === null || $payload->fileId <= 0) {
                    throw new PHPMaxException('File upload did not return a file id');
                }
            });
        });

        $this->check('video-upload', function (): void {
            if (!$this->flag('PHPMAX_UPLOAD_VIDEO')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_UPLOAD_VIDEO=1 to run real video upload check');
            }
            $video = $this->videoFixture();
            $this->withClient($this->clientOptions, function (Client $client) use ($video): void {
                $payload = $client->uploadVideo($video);
                if ($payload->videoId === null || $payload->videoId <= 0 || $payload->token === null || $payload->token === '') {
                    throw new PHPMaxException('Video upload did not return a complete attach payload');
                }
            });
        });

        $this->check('file-download-url', function (): void {
            $chatId = $this->envInt('PHPMAX_DOWNLOAD_CHAT_ID');
            $messageId = $this->envInt('PHPMAX_DOWNLOAD_MESSAGE_ID');
            $fileId = $this->envInt('PHPMAX_DOWNLOAD_FILE_ID');
            if ($chatId === null || $messageId === null || $fileId === null) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_DOWNLOAD_CHAT_ID, PHPMAX_DOWNLOAD_MESSAGE_ID and PHPMAX_DOWNLOAD_FILE_ID');
            }
            $this->withClient($this->clientOptions, function (Client $client) use ($chatId, $messageId, $fileId): void {
                $request = $client->getFileById($chatId, $messageId, $fileId);
                if ($request === null || $request->url === null || $request->url === '') {
                    throw new PHPMaxException('File download request did not return a URL');
                }
            });
        });

        $this->check('video-download-url', function (): void {
            $chatId = $this->envInt('PHPMAX_DOWNLOAD_CHAT_ID');
            $messageId = $this->envInt('PHPMAX_DOWNLOAD_MESSAGE_ID');
            $videoId = $this->envInt('PHPMAX_DOWNLOAD_VIDEO_ID');
            if ($chatId === null || $messageId === null || $videoId === null) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_DOWNLOAD_CHAT_ID, PHPMAX_DOWNLOAD_MESSAGE_ID and PHPMAX_DOWNLOAD_VIDEO_ID');
            }
            $this->withClient($this->clientOptions, function (Client $client) use ($chatId, $messageId, $videoId): void {
                $request = $client->getVideoById($chatId, $messageId, $videoId);
                if ($request === null || $request->url === null || $request->url === '') {
                    throw new PHPMaxException('Video download request did not return a URL');
                }
            });
        });

        $this->check('websocket-login', function (): void {
            if (!$this->flag('PHPMAX_WEBSOCKET')) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_WEBSOCKET=1 to run real WebSocket login check');
            }
            $token = $this->env('PHPMAX_TOKEN');
            if ($token === null) {
                throw new PHPMaxIntegrationSkip('set PHPMAX_TOKEN to run WebSocket login check');
            }
            $options = $this->buildClientOptions($token, $this->env('PHPMAX_WEB_SESSION_NAME') ?: 'web-integration-session.json');
            $client = new WebClient(new ClientOptions($options));
            $client->open();
            try {
                if ($client->loginResponse() === null || $client->me() === null) {
                    throw new PHPMaxException('WebSocket login did not populate login/profile state');
                }
                $client->runFor((int) max(1, $this->envFloat('PHPMAX_WEBSOCKET_RUN_SECONDS', 1.0)));
            } finally {
                $client->close();
            }
        });

        $this->out(sprintf('Summary: %d passed, %d skipped, %d failed.', $this->passed, $this->skipped, $this->failed));

        return $this->failed > 0 ? 1 : 0;
    }

    private function prepareEnabledRun(?string $token): void
    {
        $this->workDir = $this->env('PHPMAX_WORKDIR') ?: sys_get_temp_dir() . '/phpmax-integration';
        $this->ensureWorkDir($this->workDir);
        $this->validateConfiguredEnvValues();
        $this->clientOptions = $this->buildClientOptions($token, $this->env('PHPMAX_SESSION_NAME') ?: 'integration-session.json');
        $this->validateClientConstruction($this->clientOptions);

        if ($this->flag('PHPMAX_WEBSOCKET')) {
            $webOptions = $this->buildClientOptions($token, $this->env('PHPMAX_WEB_SESSION_NAME') ?: 'web-integration-session.json');
            $this->validateWebClientConstruction($webOptions);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function withClient(array $options, callable $callback): void
    {
        $client = new Client(new ClientOptions($options));
        $client->open();
        try {
            $callback($client);
        } finally {
            $client->close();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildClientOptions(?string $token, string $sessionName): array
    {
        $uploadChunkSize = $this->envInt('PHPMAX_UPLOAD_CHUNK_SIZE');
        $options = [
            'workDir' => $this->workDir,
            'sessionName' => $sessionName,
            'requestTimeout' => $this->envFloat('PHPMAX_REQUEST_TIMEOUT', 30.0),
            'connectTimeout' => $this->envFloat('PHPMAX_CONNECT_TIMEOUT', 30.0),
            'uploadProcessingTimeout' => $this->envFloat('PHPMAX_UPLOAD_PROCESSING_TIMEOUT', 60.0),
            'uploadHttpTimeout' => $this->envFloat('PHPMAX_UPLOAD_HTTP_TIMEOUT', 900.0),
            'uploadChunkSize' => $uploadChunkSize !== null ? $uploadChunkSize : 1048576,
            'pingInterval' => $this->envFloat('PHPMAX_PING_INTERVAL', 30.0),
        ];

        if ($token !== null) {
            $options['token'] = $token;
        } elseif ($this->flag('PHPMAX_AUTH_SMS')) {
            $options['authFlow'] = new SmsAuthFlow(new ConsoleSmsCodeProvider());
        }

        $phone = $this->env('PHPMAX_PHONE');
        if ($phone !== null) {
            $options['phone'] = $phone;
        }
        $proxy = $this->env('PHPMAX_PROXY');
        if ($proxy !== null) {
            $options['proxy'] = $proxy;
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSavedSessionOptions(string $sessionName): array
    {
        $options = $this->buildClientOptions(null, $sessionName);
        unset($options['authFlow'], $options['token']);

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validateClientConstruction(array $options): void
    {
        $client = new Client(new ClientOptions($options));
        $client->close();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function validateWebClientConstruction(array $options): void
    {
        $client = new WebClient(new ClientOptions($options));
        $client->close();
    }

    private function validateConfiguredEnvValues(): void
    {
        if ($this->flag('PHPMAX_AUTH_SMS') && $this->env('PHPMAX_PHONE') === null) {
            throw new RuntimeException('PHPMAX_PHONE is required when PHPMAX_AUTH_SMS=1');
        }

        foreach ([
            'PHPMAX_BOT_CHAT_ID',
            'PHPMAX_DOWNLOAD_CHAT_ID',
        ] as $name) {
            $this->envInt($name);
        }

        foreach ([
            'PHPMAX_BOT_ID',
            'PHPMAX_DOWNLOAD_MESSAGE_ID',
            'PHPMAX_DOWNLOAD_FILE_ID',
            'PHPMAX_DOWNLOAD_VIDEO_ID',
            'PHPMAX_UPLOAD_CHUNK_SIZE',
        ] as $name) {
            $this->requirePositiveIntEnv($name);
        }

        foreach ([
            'PHPMAX_REQUEST_TIMEOUT',
            'PHPMAX_CONNECT_TIMEOUT',
            'PHPMAX_UPLOAD_PROCESSING_TIMEOUT',
            'PHPMAX_UPLOAD_HTTP_TIMEOUT',
            'PHPMAX_WEBSOCKET_RUN_SECONDS',
        ] as $name) {
            $this->requirePositiveFloatEnv($name);
        }
        $this->requireNonNegativeFloatEnv('PHPMAX_PING_INTERVAL');

        foreach ([
            'PHPMAX_UPLOAD_PHOTO_PATH',
            'PHPMAX_UPLOAD_FILE_PATH',
            'PHPMAX_UPLOAD_VIDEO_PATH',
        ] as $name) {
            $path = $this->env($name);
            if ($path !== null && (!is_file($path) || !is_readable($path))) {
                throw new RuntimeException($name . ' must point to a readable file');
            }
        }

        if ($this->flag('PHPMAX_UPLOAD_VIDEO') && $this->env('PHPMAX_UPLOAD_VIDEO_PATH') === null) {
            throw new RuntimeException('PHPMAX_UPLOAD_VIDEO_PATH is required when PHPMAX_UPLOAD_VIDEO=1');
        }
    }

    private function requirePositiveIntEnv(string $name): void
    {
        $value = $this->envInt($name);
        if ($value !== null && $value <= 0) {
            throw new RuntimeException($name . ' must be a positive integer');
        }
    }

    private function requirePositiveFloatEnv(string $name): void
    {
        if ($this->env($name) === null) {
            return;
        }

        if ($this->envFloat($name, 1.0) <= 0.0) {
            throw new RuntimeException($name . ' must be greater than 0');
        }
    }

    private function requireNonNegativeFloatEnv(string $name): void
    {
        if ($this->env($name) === null) {
            return;
        }

        if ($this->envFloat($name, 0.0) < 0.0) {
            throw new RuntimeException($name . ' must be greater than or equal to 0');
        }
    }

    private function check(string $name, callable $callback): void
    {
        $this->out('RUN ' . $name);
        try {
            $callback();
            $this->passed++;
            $this->out('OK  ' . $name);
        } catch (PHPMaxIntegrationSkip $e) {
            $this->skipped++;
            $this->out('SKIP ' . $name . ': ' . $this->redactSecrets($e->getMessage()));
        } catch (Throwable $e) {
            $this->failed++;
            $this->err('FAIL ' . $name . ': ' . get_class($e) . ': ' . $this->redactSecrets($e->getMessage()));
        }
    }

    private function photoFixture(): Photo
    {
        $path = $this->env('PHPMAX_UPLOAD_PHOTO_PATH');
        if ($path !== null) {
            return Photo::fromPath($path);
        }

        return Photo::fromRaw($this->tinyPng(), 'phpmax-integration.png');
    }

    private function fileFixture(): File
    {
        $path = $this->env('PHPMAX_UPLOAD_FILE_PATH');
        if ($path !== null) {
            return File::fromPath($path);
        }

        return File::fromRaw('PHPMax integration file fixture' . "\n", 'phpmax-integration.txt');
    }

    private function videoFixture(): Video
    {
        $path = $this->env('PHPMAX_UPLOAD_VIDEO_PATH');
        if ($path !== null) {
            return Video::fromPath($path);
        }

        throw new PHPMaxIntegrationSkip('set PHPMAX_UPLOAD_VIDEO_PATH to a small video file before enabling video upload');
    }

    private function shouldPrintPlan(): bool
    {
        global $argv;

        if ($this->flag('PHPMAX_INTEGRATION_PLAN')) {
            return true;
        }

        return in_array('--plan', array_slice($argv, 1), true);
    }

    private function printPlan(): void
    {
        $this->out('PHPMax integration plan (no network requests).');
        $this->out('Run token checks with: PHPMAX_INTEGRATION=1 PHPMAX_TOKEN=<token> just integration-check');
        $this->out('Run interactive SMS checks with: PHPMAX_INTEGRATION=1 PHPMAX_AUTH_SMS=1 PHPMAX_PHONE=<phone> just integration-check');
        $this->out('');
        $this->out('Base check:');
        $this->out('  tcp-login: requires PHPMAX_TOKEN or PHPMAX_AUTH_SMS=1 with PHPMAX_PHONE');
        $this->out('  local-session-stored: verifies token/device/phone session persistence');
        $this->out('  saved-session-login: reopens from saved session without token/SMS auth flow');
        $this->out('');
        $this->out('Optional checks:');
        foreach ($this->integrationPlanRows() as $row) {
            $status = $this->planRowConfigured($row['vars']) ? 'ready' : 'needs env';
            $this->out(sprintf('  %-22s %s (%s)', $row['name'] . ':', $status, implode(', ', $row['vars'])));
        }
        $this->out('');
        $this->out('Shared options are read when set, but values are never printed:');
        $this->out('  PHPMAX_WORKDIR, PHPMAX_SESSION_NAME, PHPMAX_WEB_SESSION_NAME, PHPMAX_PHONE, PHPMAX_PROXY,');
        $this->out('  PHPMAX_REQUEST_TIMEOUT, PHPMAX_CONNECT_TIMEOUT, PHPMAX_PING_INTERVAL,');
        $this->out('  PHPMAX_UPLOAD_PROCESSING_TIMEOUT, PHPMAX_UPLOAD_HTTP_TIMEOUT, PHPMAX_UPLOAD_CHUNK_SIZE');
    }

    /**
     * @return array<int, array{name: string, vars: array<int, string>}>
     */
    private function integrationPlanRows(): array
    {
        return [
            ['name' => 'fetch-chats', 'vars' => ['PHPMAX_FETCH_CHATS=1']],
            ['name' => 'sessions-list', 'vars' => ['PHPMAX_FETCH_SESSIONS=1']],
            ['name' => 'telemetry-login', 'vars' => ['PHPMAX_TELEMETRY_LOGIN=1']],
            ['name' => 'telemetry-navigation', 'vars' => ['PHPMAX_TELEMETRY_NAVIGATION=1']],
            ['name' => 'bot-init-data', 'vars' => ['PHPMAX_BOT_ID']],
            ['name' => 'photo-upload', 'vars' => ['PHPMAX_UPLOAD_PHOTO=1']],
            ['name' => 'file-upload', 'vars' => ['PHPMAX_UPLOAD_FILE=1']],
            ['name' => 'video-upload', 'vars' => ['PHPMAX_UPLOAD_VIDEO=1', 'PHPMAX_UPLOAD_VIDEO_PATH']],
            ['name' => 'file-download-url', 'vars' => ['PHPMAX_DOWNLOAD_CHAT_ID', 'PHPMAX_DOWNLOAD_MESSAGE_ID', 'PHPMAX_DOWNLOAD_FILE_ID']],
            ['name' => 'video-download-url', 'vars' => ['PHPMAX_DOWNLOAD_CHAT_ID', 'PHPMAX_DOWNLOAD_MESSAGE_ID', 'PHPMAX_DOWNLOAD_VIDEO_ID']],
            ['name' => 'websocket-login', 'vars' => ['PHPMAX_WEBSOCKET=1']],
        ];
    }

    /**
     * @param array<int, string> $requirements
     */
    private function planRowConfigured(array $requirements): bool
    {
        foreach ($requirements as $requirement) {
            if (substr($requirement, -2) === '=1') {
                $name = substr($requirement, 0, -2);
                if (!$this->flag($name)) {
                    return false;
                }
                continue;
            }

            if ($this->env($requirement) === null) {
                return false;
            }
        }

        return true;
    }

    private function tinyPng(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        );
    }

    private function ensureWorkDir(string $dir): void
    {
        if (file_exists($dir) && !is_dir($dir)) {
            throw new RuntimeException('Integration workdir is not a directory: ' . $dir);
        }
        if (is_dir($dir)) {
            if (!is_writable($dir)) {
                throw new RuntimeException('Integration workdir is not writable: ' . $dir);
            }
            return;
        }
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create integration workdir: ' . $dir);
        }
        @chmod($dir, 0700);
        if (!is_writable($dir)) {
            throw new RuntimeException('Integration workdir is not writable: ' . $dir);
        }
    }

    private function flag(string $name): bool
    {
        $value = $this->env($name);
        if ($value === null) {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function envInt(string $name): ?int
    {
        $value = $this->env($name);
        if ($value === null) {
            return null;
        }
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new RuntimeException($name . ' must be an integer');
        }

        return (int) $value;
    }

    private function envFloat(string $name, float $default): float
    {
        $value = $this->env($name);
        if ($value === null) {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new RuntimeException($name . ' must be numeric');
        }

        return (float) $value;
    }

    private function out(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    private function err(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    private function redactSecrets(string $message): string
    {
        foreach ([
            'PHPMAX_TOKEN',
            'PHPMAX_PHONE',
        ] as $name) {
            $value = $this->env($name);
            if ($value !== null) {
                $message = str_replace($value, '<redacted>', $message);
            }
        }

        $proxy = $this->env('PHPMAX_PROXY');
        if ($proxy !== null) {
            $message = str_replace($proxy, '<redacted>', $message);
            $parts = parse_url($proxy);
            if (is_array($parts)) {
                foreach (['user', 'pass'] as $key) {
                    if (isset($parts[$key]) && $parts[$key] !== '') {
                        $encoded = (string) $parts[$key];
                        $decoded = rawurldecode($encoded);
                        $message = str_replace($encoded, '<redacted>', $message);
                        $message = str_replace($decoded, '<redacted>', $message);
                    }
                }
            }
        }

        return $message;
    }
}

exit((new PHPMaxIntegrationRunner())->run());
