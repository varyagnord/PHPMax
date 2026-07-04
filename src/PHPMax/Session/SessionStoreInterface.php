<?php

declare(strict_types=1);

namespace PHPMax\Session;

interface SessionStoreInterface
{
    public function saveSession(SessionInfo $sessionInfo): void;

    public function updateToken(string $oldToken, string $newToken): void;

    public function loadSession(): ?SessionInfo;

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo;

    public function loadSessionByPhone(string $phone): ?SessionInfo;

    public function deleteSession(string $token): void;

    public function deleteAllSessions(): void;

    public function close(): void;
}
