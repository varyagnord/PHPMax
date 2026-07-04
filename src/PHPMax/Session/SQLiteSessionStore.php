<?php

declare(strict_types=1);

namespace PHPMax\Session;

use PDO;
use PDOException;
use PHPMax\Domain\SyncState;
use PHPMax\Exception\PHPMaxException;

class SQLiteSessionStore implements SessionStoreInterface
{
    /** @var string */
    private $path;
    /** @var PDO|null */
    private $pdo;

    public function __construct(string $workDir, string $fileName = 'session.db')
    {
        $this->assertSafeFileName($fileName);

        if (!class_exists(PDO::class) || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new PHPMaxException('PDO SQLite driver is required for SQLiteSessionStore');
        }

        if (!is_dir($workDir) && !mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            throw new PHPMaxException('Unable to create session directory: ' . $workDir);
        }
        if (!is_writable($workDir)) {
            throw new PHPMaxException('Session directory is not writable: ' . $workDir);
        }

        $this->path = rtrim($workDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $this->pdo = null;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
        $sync = $sessionInfo->sync instanceof SyncState ? $sessionInfo->sync : new SyncState();

        $this->execute(
            'INSERT OR REPLACE INTO sessions (
                token,
                device_id,
                phone,
                mt_instance_id,
                chats_sync,
                contacts_sync,
                drafts_sync,
                presence_sync,
                config_hash
            ) VALUES (
                :token,
                :device_id,
                :phone,
                :mt_instance_id,
                :chats_sync,
                :contacts_sync,
                :drafts_sync,
                :presence_sync,
                :config_hash
            )',
            [
                ':token' => (string) $sessionInfo->token,
                ':device_id' => (string) $sessionInfo->deviceId,
                ':phone' => (string) $sessionInfo->phone,
                ':mt_instance_id' => (string) $sessionInfo->mtInstanceId,
                ':chats_sync' => (int) $sync->chatsSync,
                ':contacts_sync' => (int) $sync->contactsSync,
                ':drafts_sync' => (int) $sync->draftsSync,
                ':presence_sync' => (int) $sync->presenceSync,
                ':config_hash' => (string) $sync->configHash,
            ]
        );
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
        $this->execute(
            'UPDATE sessions SET token = :new_token WHERE token = :old_token',
            [
                ':new_token' => $newToken,
                ':old_token' => $oldToken,
            ]
        );
    }

    public function loadSession(): ?SessionInfo
    {
        $row = $this->fetchOne(
            'SELECT token, device_id, phone, mt_instance_id, chats_sync, contacts_sync, drafts_sync, presence_sync, config_hash
             FROM sessions
             ORDER BY rowid ASC
             LIMIT 1',
            []
        );

        return $row === null ? null : $this->rowToSession($row);
    }

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo
    {
        $row = $this->fetchOne(
            'SELECT token, device_id, phone, mt_instance_id, chats_sync, contacts_sync, drafts_sync, presence_sync, config_hash
             FROM sessions
             WHERE device_id = :device_id
             ORDER BY rowid ASC
             LIMIT 1',
            [':device_id' => $deviceId]
        );

        return $row === null ? null : $this->rowToSession($row);
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        $row = $this->fetchOne(
            'SELECT token, device_id, phone, mt_instance_id, chats_sync, contacts_sync, drafts_sync, presence_sync, config_hash
             FROM sessions
             WHERE phone = :phone
             ORDER BY rowid ASC
             LIMIT 1',
            [':phone' => $phone]
        );

        return $row === null ? null : $this->rowToSession($row);
    }

    public function deleteSession(string $token): void
    {
        $this->execute('DELETE FROM sessions WHERE token = :token', [':token' => $token]);
    }

    public function deleteAllSessions(): void
    {
        $this->execute('DELETE FROM sessions');
    }

    public function close(): void
    {
        $this->pdo = null;
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
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, array $parameters): ?array
    {
        try {
            $statement = $this->connection()->prepare($sql);
            $statement->execute($parameters);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new PHPMaxException('SQLite session store query failed: ' . $e->getMessage(), 0, $e);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function execute(string $sql, array $parameters = []): void
    {
        try {
            $statement = $this->connection()->prepare($sql);
            $statement->execute($parameters);
        } catch (PDOException $e) {
            throw new PHPMaxException('SQLite session store write failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        try {
            $this->pdo = new PDO('sqlite:' . $this->path);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            $this->initializeDatabase($this->pdo);
        } catch (PDOException $e) {
            $this->pdo = null;
            throw new PHPMaxException('Unable to open SQLite session store: ' . $this->path, 0, $e);
        }

        @chmod($this->path, 0600);

        return $this->pdo;
    }

    private function initializeDatabase(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
                token TEXT NOT NULL PRIMARY KEY,
                device_id TEXT NOT NULL,
                phone TEXT NOT NULL,
                mt_instance_id TEXT NOT NULL DEFAULT \'\',
                chats_sync INTEGER NOT NULL DEFAULT -1,
                contacts_sync INTEGER NOT NULL DEFAULT -1,
                drafts_sync INTEGER NOT NULL DEFAULT -1,
                presence_sync INTEGER NOT NULL DEFAULT -1,
                config_hash TEXT NOT NULL DEFAULT \'\'
            )'
        );

        $this->ensureColumn($pdo, 'mt_instance_id', 'TEXT NOT NULL DEFAULT \'\'');
        $this->ensureColumn($pdo, 'chats_sync', 'INTEGER NOT NULL DEFAULT -1');
        $this->ensureColumn($pdo, 'contacts_sync', 'INTEGER NOT NULL DEFAULT -1');
        $this->ensureColumn($pdo, 'drafts_sync', 'INTEGER NOT NULL DEFAULT -1');
        $this->ensureColumn($pdo, 'presence_sync', 'INTEGER NOT NULL DEFAULT -1');
        $this->ensureColumn($pdo, 'config_hash', 'TEXT NOT NULL DEFAULT \'\'');

        $statement = $pdo->prepare('UPDATE sessions SET config_hash = :hash WHERE config_hash = \'\'');
        $statement->execute([':hash' => SyncState::DEFAULT_CONFIG_HASH]);
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_device_id ON sessions (device_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sessions_phone ON sessions (phone)');
    }

    private function ensureColumn(PDO $pdo, string $name, string $definition): void
    {
        $statement = $pdo->query('PRAGMA table_info(sessions)');
        if ($statement === false) {
            throw new PHPMaxException('Unable to inspect SQLite session table');
        }

        $columns = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($row['name'])) {
                $columns[(string) $row['name']] = true;
            }
        }

        if (!isset($columns[$name])) {
            $pdo->exec('ALTER TABLE sessions ADD COLUMN ' . $name . ' ' . $definition);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToSession(array $row): SessionInfo
    {
        return new SessionInfo([
            'token' => (string) $row['token'],
            'deviceId' => (string) $row['device_id'],
            'phone' => (string) $row['phone'],
            'mtInstanceId' => (string) ($row['mt_instance_id'] ?? ''),
            'sync' => new SyncState([
                'chatsSync' => (int) ($row['chats_sync'] ?? -1),
                'contactsSync' => (int) ($row['contacts_sync'] ?? -1),
                'draftsSync' => (int) ($row['drafts_sync'] ?? -1),
                'presenceSync' => (int) ($row['presence_sync'] ?? -1),
                'configHash' => ($row['config_hash'] ?? '') !== ''
                    ? $row['config_hash']
                    : SyncState::DEFAULT_CONFIG_HASH,
            ]),
        ]);
    }
}
