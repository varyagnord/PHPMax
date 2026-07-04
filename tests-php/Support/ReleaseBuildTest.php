<?php

declare(strict_types=1);

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $repoRoot = dirname(__DIR__, 2);
    $php = escapeshellarg(PHP_BINARY);
    $tool = escapeshellarg($repoRoot . '/tools/build-release.php');
    $removeTree = static function (string $path) use (&$removeTree): void {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    };

    $dryRunOutput = [];
    $dryRunExit = 1;
    exec($php . ' ' . $tool . ' --dry-run', $dryRunOutput, $dryRunExit);
    $assertSame(0, $dryRunExit, 'release dry-run must succeed');
    $spec = json_decode(implode("\n", $dryRunOutput), true);
    $assert(is_array($spec), 'release dry-run must return JSON');
    $assertSame(false, $spec['vendor_included']);
    $assertSame([], $spec['runtime_packages']);
    $assert(in_array('autoload.php', $spec['files'], true));
    $assert(in_array('composer.json', $spec['files'], true));
    $assert(in_array('src/PHPMax/Client.php', $spec['files'], true));
    $assert(in_array('src/PHPMax/Runtime/App.php', $spec['files'], true));
    $assert(in_array('docs/phpmax/README.md', $spec['files'], true));
    $assert(!in_array('src/pymax/app.py', $spec['files'], true));
    $assert(!in_array('tests-php/bootstrap.php', $spec['files'], true));

    $readme = (string) file_get_contents($repoRoot . '/README.md');
    $assert(strpos($readme, '# PHPMax') !== false, 'root README must document PHPMax');
    $assert(strpos($readme, 'composer require varyagnord/phpmax') !== false, 'root README must include PHP installation path');
    $assert(strpos($readme, 'pip install') === false, 'root README must not ship Python install instructions');

    $checkOutput = [];
    $checkExit = 1;
    exec($php . ' ' . $tool . ' --check', $checkOutput, $checkExit);
    $assertSame(0, $checkExit, 'release check must validate manifest without building ZIP');
    $assertSame('Release spec is valid.', implode("\n", $checkOutput));

    $conflictOutput = [];
    $conflictExit = 0;
    exec($php . ' ' . $tool . ' --check --dry-run 2>&1', $conflictOutput, $conflictExit);
    $assertSame(2, $conflictExit, 'release builder must reject conflicting check/dry-run modes');
    $assert(strpos(implode("\n", $conflictOutput), 'cannot be used together') !== false, 'release builder conflict error must be diagnostic');

    if (!class_exists('ZipArchive') && trim((string) shell_exec('command -v zip 2>/dev/null')) === '') {
        $assert(true, 'No ZIP backend available; archive build check skipped');
        return;
    }
    if (trim((string) shell_exec('command -v unzip 2>/dev/null')) === '') {
        $assert(true, 'No unzip command available; archive content check skipped');
        return;
    }

    $dir = sys_get_temp_dir() . '/phpmax-release-test-' . getmypid() . '-' . mt_rand();
    $zipPath = $dir . '/phpmax-test.zip';
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create release test directory');
    }

    try {
        $buildOutput = [];
        $buildExit = 1;
        exec($php . ' ' . $tool . ' --output=' . escapeshellarg($zipPath), $buildOutput, $buildExit);
        $assertSame(0, $buildExit, 'release archive build must succeed');
        $assert(is_file($zipPath), 'release archive must exist');

        $listOutput = [];
        $listExit = 1;
        exec('unzip -Z1 ' . escapeshellarg($zipPath), $listOutput, $listExit);
        $assertSame(0, $listExit, 'release archive listing must succeed');
        $assert(in_array('autoload.php', $listOutput, true));
        $assert(in_array('composer.json', $listOutput, true));
        $assert(in_array('src/PHPMax/Client.php', $listOutput, true));
        $assert(in_array('docs/phpmax/README.md', $listOutput, true));
        $assert(!in_array('src/pymax/app.py', $listOutput, true));
        $assert(!in_array('tests-php/bootstrap.php', $listOutput, true));

        $extractDir = $dir . '/extract';
        $extractOutput = [];
        $extractExit = 1;
        exec('unzip -q ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($extractDir), $extractOutput, $extractExit);
        $assertSame(0, $extractExit, 'release archive extraction must succeed');
        $archiveReadme = (string) file_get_contents($extractDir . '/README.md');
        $assert(strpos($archiveReadme, '# PHPMax') !== false, 'release README must document PHPMax');
        $assert(strpos($archiveReadme, 'pip install') === false, 'release README must not document Python installation');
        $smokeCode = 'require ' . var_export($extractDir . '/autoload.php', true) . ';'
            . '$dir = sys_get_temp_dir() . "/phpmax-release-smoke-" . getmypid();'
            . 'if (!is_dir($dir) && !mkdir($dir, 0700, true)) { exit(2); }'
            . 'register_shutdown_function(static function () use ($dir): void { foreach (glob($dir . "/*") ?: [] as $path) { @unlink($path); } @rmdir($dir); });'
            . '$store = new PHPMax\\Session\\JsonFileSessionStore($dir, "session.json");'
            . '$store->saveSession(new PHPMax\\Session\\SessionInfo(["token" => "token", "deviceId" => "device", "phone" => "+10000000000"]));'
            . 'if (!$store->loadSession() instanceof PHPMax\\Session\\SessionInfo) { exit(3); }'
            . '$store->deleteAllSessions();'
            . '$store->close();'
            . '$options = new PHPMax\\Config\\ClientOptions(["token" => "token", "store" => $store]);'
            . 'if ($options->token !== "token") { exit(4); }'
            . '$client = new PHPMax\\Client($options);'
            . 'if (!$client instanceof PHPMax\\Client) { exit(5); }'
            . '$web = new PHPMax\\WebClient(new PHPMax\\Config\\ClientOptions(["token" => "token", "workDir" => $dir, "sessionName" => "web-session.json"]));'
            . 'if (!$web instanceof PHPMax\\WebClient) { exit(6); }'
            . '$tcp = new PHPMax\\Transport\\TcpTransport("api.oneme.ru", 443, true, 0.001);'
            . '$ws = new PHPMax\\Transport\\WebSocketTransport("wss://ws-api.oneme.ru/websocket", 0.001);'
            . 'if (!$tcp instanceof PHPMax\\Transport\\TcpTransport || !$ws instanceof PHPMax\\Transport\\WebSocketTransport) { exit(7); }'
            . '$file = PHPMax\\Files\\File::fromRaw("abc", "a.txt");'
            . 'if ($file->size() !== 3 || $file->name() !== "a.txt") { exit(8); }'
            . '$photo = PHPMax\\Files\\Photo::fromRaw("abc", "avatar.png");'
            . 'if ($photo->validatePhoto()[0] !== "png") { exit(9); }'
            . 'echo "release autoload ok\n";';
        $smokeOutput = [];
        $smokeExit = 1;
        exec($php . ' -r ' . escapeshellarg($smokeCode), $smokeOutput, $smokeExit);
        $assertSame(0, $smokeExit, 'release fallback autoload must load runtime classes from extracted archive');
        $assertSame('release autoload ok', implode("\n", $smokeOutput));
    } finally {
        if (isset($extractDir)) {
            $removeTree($extractDir);
        }
        @unlink($zipPath);
        @rmdir($dir);
    }
};
