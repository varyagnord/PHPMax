<?php

declare(strict_types=1);

use PHPMax\Domain\SyncState;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Session\JsonFileSessionStore;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;
use PHPMax\Session\SQLiteSessionStore;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $cleanupDir = static function (string $dir): void {
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    };

    $exerciseStore = static function (SessionStoreInterface $store) use ($assert, $assertSame): void {
        $session = new SessionInfo([
            'token' => 'token-1',
            'deviceId' => 'device-1',
            'phone' => '+79990000000',
            'mtInstanceId' => 'mt-1',
            'sync' => new SyncState([
                'chatsSync' => 10,
                'contactsSync' => 20,
                'draftsSync' => 30,
                'presenceSync' => 40,
                'configHash' => 'config-1',
            ]),
        ]);

        $store->saveSession($session);
        $loaded = $store->loadSession();
        $assert($loaded instanceof SessionInfo);
        $assertSame('token-1', $loaded->token);
        $assertSame('device-1', $store->loadSessionByDeviceId('device-1')->deviceId);
        $assertSame('+79990000000', $store->loadSessionByPhone('+79990000000')->phone);
        $assertSame(10, $loaded->sync->chatsSync);
        $assertSame(20, $loaded->sync->contactsSync);
        $assertSame(30, $loaded->sync->draftsSync);
        $assertSame(40, $loaded->sync->presenceSync);
        $assertSame('config-1', $loaded->sync->configHash);

        $otherSession = new SessionInfo([
            'token' => 'token-other',
            'deviceId' => 'device-other',
            'phone' => '+79990000001',
        ]);
        $store->saveSession($otherSession);
        $assertSame('token-other', $store->loadSessionByDeviceId('device-other')->token);

        $store->updateToken('token-1', 'token-2');
        $updated = $store->loadSessionByDeviceId('device-1');
        $assert($updated instanceof SessionInfo);
        $assertSame('token-2', $updated->token);
        $assertSame(10, $updated->sync->chatsSync);

        $store->close();
        $assertSame('token-2', $store->loadSessionByPhone('+79990000000')->token);

        $store->deleteSession('token-2');
        $assertSame(null, $store->loadSessionByDeviceId('device-1'));
        $assertSame('token-other', $store->loadSessionByDeviceId('device-other')->token);
        $store->deleteAllSessions();
        $assertSame(null, $store->loadSessionByDeviceId('device-other'));
        $assertSame(null, $store->loadSessionByPhone('+79990000001'));
        $assertSame(null, $store->loadSession());
        $store->close();
    };

    $assertRejectsUnsafeFileNames = static function (callable $factory, string $prefix) use ($assertThrows, $cleanupDir): void {
        $unsafeNames = [
            '',
            '.',
            '..',
            '../session.json',
            'nested/session.json',
            'nested\\session.json',
            "session\0.json",
        ];

        foreach ($unsafeNames as $unsafeName) {
            $dir = sys_get_temp_dir() . '/phpmax-session-unsafe-test-' . $prefix . '-' . getmypid() . '-' . mt_rand();
            try {
                $assertThrows(PHPMaxException::class, static function () use ($factory, $dir, $unsafeName): void {
                    $factory($dir, $unsafeName);
                }, 'Unsafe session file name must be rejected');
            } finally {
                $cleanupDir($dir);
            }
        }
    };

    $jsonDir = sys_get_temp_dir() . '/phpmax-session-json-test-' . getmypid() . '-' . mt_rand();
    try {
        $exerciseStore(new JsonFileSessionStore($jsonDir));
    } finally {
        $cleanupDir($jsonDir);
    }
    $assertRejectsUnsafeFileNames(static function (string $dir, string $fileName): void {
        new JsonFileSessionStore($dir, $fileName);
    }, 'json');

    $sqliteDir = sys_get_temp_dir() . '/phpmax-session-sqlite-test-' . getmypid() . '-' . mt_rand();
    if (class_exists(PDO::class) && in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        try {
            $exerciseStore(new SQLiteSessionStore($sqliteDir));
        } finally {
            $cleanupDir($sqliteDir);
        }
    } else {
        $assertThrows(PHPMaxException::class, static function () use ($sqliteDir): void {
            new SQLiteSessionStore($sqliteDir);
        });
    }
    $assertRejectsUnsafeFileNames(static function (string $dir, string $fileName): void {
        new SQLiteSessionStore($dir, $fileName);
    }, 'sqlite');
};
