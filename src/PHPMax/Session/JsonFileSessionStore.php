<?php

declare(strict_types=1);

namespace PHPMax\Session;

use PHPMax\Exception\PHPMaxException;

class JsonFileSessionStore implements SessionStoreInterface
{
    /** @var string */
    private $path;

    public function __construct(string $workDir, string $fileName = 'session.json')
    {
        $this->assertSafeFileName($fileName);

        if (!is_dir($workDir) && !mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            throw new PHPMaxException('Unable to create session directory: ' . $workDir);
        }
        if (!is_writable($workDir)) {
            throw new PHPMaxException('Session directory is not writable: ' . $workDir);
        }

        $this->path = rtrim($workDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
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
        foreach ($sessions as $index => $session) {
            if (($session['token'] ?? null) === $oldToken) {
                $sessions[$index]['token'] = $newToken;
                $this->writeSessions($sessions);
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
        foreach ($this->readSessions() as $session) {
            if (($session['deviceId'] ?? $session['device_id'] ?? null) === $deviceId) {
                return SessionInfo::fromArray($session);
            }
        }

        return null;
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        foreach ($this->readSessions() as $session) {
            if (($session['phone'] ?? null) === $phone) {
                return SessionInfo::fromArray($session);
            }
        }

        return null;
    }

    public function deleteSession(string $token): void
    {
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
            @chmod($tmpPath, 0600);
            if (!rename($tmpPath, $this->path)) {
                @unlink($tmpPath);
                throw new PHPMaxException('Unable to replace session store atomically: ' . $this->path);
            }
            @chmod($this->path, 0600);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
