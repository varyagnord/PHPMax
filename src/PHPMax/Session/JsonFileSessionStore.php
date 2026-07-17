<?php

declare(strict_types=1);

namespace PHPMax\Session;

use PHPMax\Exception\PHPMaxException;

class JsonFileSessionStore implements SessionStoreInterface
{
    /** @var string */
    private $path;
    /** @var bool */
    private $singleSession;

    public function __construct(string $workDir, string $fileName = 'session.json', bool $singleSession = false)
    {
        $this->assertSafeFileName($fileName);

        if (!is_dir($workDir) && !mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            throw new PHPMaxException('Unable to create session directory: ' . $workDir);
        }
        if (!is_writable($workDir)) {
            throw new PHPMaxException('Session directory is not writable: ' . $workDir);
        }

        $this->path = rtrim($workDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $this->singleSession = $singleSession;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
        if ($this->singleSession) {
            $this->writeSessions([$sessionInfo->toArray()]);
            return;
        }

        $sessions = $this->readSessions();
        $replaced = false;
        foreach ($sessions as $index => $session) {
            if (($session['token'] ?? null) === $sessionInfo->token) {
                $sessions[$index] = $sessionInfo->toArray();
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $sessions[] = $sessionInfo->toArray();
        }
        $this->writeSessions($sessions);
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
        $sessions = $this->readSessions();
        foreach ($this->sessionIndexes($sessions) as $index) {
            $session = $sessions[$index];
            if (($session['token'] ?? null) === $oldToken) {
                $sessions[$index]['token'] = $newToken;
                $this->writeSessions($this->singleSession ? [$sessions[$index]] : $sessions);
                return;
            }
        }
    }

    public function loadSession(): ?SessionInfo
    {
        $sessions = $this->readSessions();
        if ($sessions === []) {
            return null;
        }

        return SessionInfo::fromArray($sessions[0]);
    }

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo
    {
        $sessions = $this->readSessions();
        foreach ($this->sessionIndexes($sessions) as $index) {
            $session = $sessions[$index];
            if (($session['deviceId'] ?? $session['device_id'] ?? null) === $deviceId) {
                return SessionInfo::fromArray($session);
            }
        }

        return null;
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        $sessions = $this->readSessions();
        foreach ($this->sessionIndexes($sessions) as $index) {
            $session = $sessions[$index];
            if (($session['phone'] ?? null) === $phone) {
                return SessionInfo::fromArray($session);
            }
        }

        return null;
    }

    public function deleteSession(string $token): void
    {
        if ($this->singleSession) {
            $sessions = $this->readSessions();
            if ($sessions === []) {
                return;
            }
            $active = $sessions[0];
            $this->writeSessions(($active['token'] ?? null) === $token ? [] : [$active]);
            return;
        }

        $sessions = [];
        foreach ($this->readSessions() as $session) {
            if (($session['token'] ?? null) !== $token) {
                $sessions[] = $session;
            }
        }
        $this->writeSessions($sessions);
    }

    public function deleteAllSessions(): void
    {
        $this->writeSessions([]);
    }

    public function close(): void
    {
    }

    private function assertSafeFileName(string $fileName): void
    {
        if (
            $fileName === ''
            || $fileName === '.'
            || $fileName === '..'
            || strpos($fileName, '/') !== false
            || strpos($fileName, '\\') !== false
            || strpos($fileName, "\0") !== false
        ) {
            throw new PHPMaxException('Session file name must be a plain file name');
        }
    }

    /**
     * Single-session mode preserves the first legacy entry because that is
     * exactly the session older PHPMax versions loaded as active.
     *
     * @param list<array<string, mixed>> $sessions
     * @return list<int>
     */
    private function sessionIndexes(array $sessions): array
    {
        if ($this->singleSession) {
            return $sessions === [] ? [] : [0];
        }

        $indexes = array_keys($sessions);
        return $indexes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readSessions(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $json = file_get_contents($this->path);
        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new PHPMaxException('Invalid session store JSON: ' . $this->path);
        }

        if (isset($data['sessions']) && is_array($data['sessions'])) {
            return array_values(array_filter($data['sessions'], 'is_array'));
        }

        return [$data];
    }

    /**
     * @param list<array<string, mixed>> $sessions
     */
    private function writeSessions(array $sessions): void
    {
        $payload = json_encode(['sessions' => $sessions], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new PHPMaxException('Failed to encode session store JSON');
        }

        $lockPath = $this->path . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new PHPMaxException('Unable to open session lock file: ' . $lockPath);
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new PHPMaxException('Unable to lock session store: ' . $this->path);
            }

            $tmpPath = $this->path . '.' . getmypid() . '.tmp';
            if (file_put_contents($tmpPath, $payload . PHP_EOL, LOCK_EX) === false) {
                throw new PHPMaxException('Unable to write temporary session store: ' . $tmpPath);
            }
            set_error_handler(static function (): bool {
                return true;
            });
            try {
                chmod($tmpPath, 0600);
            } finally {
                restore_error_handler();
            }
            if (!rename($tmpPath, $this->path)) {
                set_error_handler(static function (): bool {
                    return true;
                });
                try {
                    unlink($tmpPath);
                } finally {
                    restore_error_handler();
                }
                throw new PHPMaxException('Unable to replace session store atomically: ' . $this->path);
            }
            set_error_handler(static function (): bool {
                return true;
            });
            try {
                chmod($this->path, 0600);
            } finally {
                restore_error_handler();
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
