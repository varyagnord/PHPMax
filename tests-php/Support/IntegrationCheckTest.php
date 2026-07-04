<?php

declare(strict_types=1);

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $command = 'PHPMAX_INTEGRATION=0 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php') . ' 2>&1';
    $output = [];
    $exitCode = 1;
    exec($command, $output, $exitCode);

    $assertSame(0, $exitCode, 'Integration check must be safe to run without credentials');
    $assert(strpos(implode("\n", $output), 'SKIP integration checks') !== false, 'Integration check must explain disabled state');

    $missingTokenCommand = 'env -u PHPMAX_TOKEN PHPMAX_INTEGRATION=1 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php') . ' 2>&1';
    $missingTokenOutput = [];
    $missingTokenExitCode = 0;
    exec($missingTokenCommand, $missingTokenOutput, $missingTokenExitCode);

    $assertSame(2, $missingTokenExitCode, 'Integration check must fail fast when explicitly enabled without token');
    $assert(strpos(implode("\n", $missingTokenOutput), 'PHPMAX_TOKEN is required') !== false, 'Integration check must explain missing token');

    $smsPhoneCommand = 'env -u PHPMAX_TOKEN PHPMAX_INTEGRATION=1'
        . ' PHPMAX_AUTH_SMS=1'
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $smsPhoneOutput = [];
    $smsPhoneExitCode = 0;
    exec($smsPhoneCommand, $smsPhoneOutput, $smsPhoneExitCode);
    $smsPhoneText = implode("\n", $smsPhoneOutput);

    $assertSame(2, $smsPhoneExitCode, 'Interactive SMS integration mode must fail fast without phone before network checks');
    $assert(strpos($smsPhoneText, 'PHPMAX_PHONE is required when PHPMAX_AUTH_SMS=1') !== false, 'SMS auth preflight must explain missing phone');

    $planCommand = 'PHPMAX_INTEGRATION_PLAN=1 PHPMAX_TOKEN=secret-token PHPMAX_PROXY=http://user:secret-pass@127.0.0.1:8080 PHPMAX_UPLOAD_PHOTO=1 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php') . ' 2>&1';
    $planOutput = [];
    $planExitCode = 1;
    exec($planCommand, $planOutput, $planExitCode);
    $planText = implode("\n", $planOutput);

    $assertSame(0, $planExitCode, 'Integration plan must be safe without real network checks');
    $assert(strpos($planText, 'PHPMax integration plan') !== false, 'Integration plan must explain what it prints');
    $assert(strpos($planText, 'PHPMAX_AUTH_SMS=1') !== false, 'Integration plan must document interactive SMS auth mode');
    $assert(strpos($planText, 'saved-session-login') !== false, 'Integration plan must document saved-session reuse check');
    $assert(strpos($planText, 'photo-upload:') !== false && strpos($planText, 'ready') !== false, 'Integration plan must mark configured optional checks');
    $assert(strpos($planText, 'secret-token') === false, 'Integration plan must not print token values');
    $assert(strpos($planText, 'secret-pass') === false, 'Integration plan must not print proxy credentials');

    $preflightCommand = 'PHPMAX_INTEGRATION=1'
        . ' PHPMAX_TOKEN=secret-token'
        . ' PHPMAX_PROXY=' . escapeshellarg('http://user:secret-pass@127.0.0.1:8080')
        . ' PHPMAX_SESSION_NAME=' . escapeshellarg('../session.json')
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $preflightOutput = [];
    $preflightExitCode = 0;
    exec($preflightCommand, $preflightOutput, $preflightExitCode);
    $preflightText = implode("\n", $preflightOutput);

    $assertSame(2, $preflightExitCode, 'Integration check must fail fast on invalid enabled-run configuration before network checks');
    $assert(strpos($preflightText, 'FAIL integration preflight') !== false, 'Integration preflight failure must be explicit');
    $assert(strpos($preflightText, 'Session file name must be a plain file name') !== false, 'Integration preflight must surface invalid session name');
    $assert(strpos($preflightText, 'secret-token') === false, 'Integration preflight must not print token values');
    $assert(strpos($preflightText, 'secret-pass') === false, 'Integration preflight must not print proxy credentials');

    $numericCommand = 'PHPMAX_INTEGRATION=1'
        . ' PHPMAX_TOKEN=secret-token'
        . ' PHPMAX_UPLOAD_CHUNK_SIZE=not-a-number'
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $numericOutput = [];
    $numericExitCode = 0;
    exec($numericCommand, $numericOutput, $numericExitCode);
    $numericText = implode("\n", $numericOutput);

    $assertSame(2, $numericExitCode, 'Integration check must fail fast on malformed numeric env before network checks');
    $assert(strpos($numericText, 'PHPMAX_UPLOAD_CHUNK_SIZE must be an integer') !== false, 'Integration preflight must explain malformed numeric env');
    $assert(strpos($numericText, 'secret-token') === false, 'Integration numeric preflight must not print token values');

    $boundsCommand = 'PHPMAX_INTEGRATION=1'
        . ' PHPMAX_TOKEN=secret-token'
        . ' PHPMAX_UPLOAD_CHUNK_SIZE=0'
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $boundsOutput = [];
    $boundsExitCode = 0;
    exec($boundsCommand, $boundsOutput, $boundsExitCode);
    $boundsText = implode("\n", $boundsOutput);

    $assertSame(2, $boundsExitCode, 'Integration check must reject non-positive upload chunk size before network checks');
    $assert(strpos($boundsText, 'PHPMAX_UPLOAD_CHUNK_SIZE must be a positive integer') !== false, 'Integration preflight must explain non-positive upload chunk size');
    $assert(strpos($boundsText, 'secret-token') === false, 'Integration bounds preflight must not print token values');

    $timeoutCommand = 'PHPMAX_INTEGRATION=1'
        . ' PHPMAX_TOKEN=secret-token'
        . ' PHPMAX_REQUEST_TIMEOUT=0'
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $timeoutOutput = [];
    $timeoutExitCode = 0;
    exec($timeoutCommand, $timeoutOutput, $timeoutExitCode);
    $timeoutText = implode("\n", $timeoutOutput);

    $assertSame(2, $timeoutExitCode, 'Integration check must reject zero request timeout before network checks');
    $assert(strpos($timeoutText, 'PHPMAX_REQUEST_TIMEOUT must be greater than 0') !== false, 'Integration preflight must explain zero request timeout');
    $assert(strpos($timeoutText, 'secret-token') === false, 'Integration timeout preflight must not print token values');

    $pingCommand = 'PHPMAX_INTEGRATION=1'
        . ' PHPMAX_TOKEN=secret-token'
        . ' PHPMAX_PING_INTERVAL=-1'
        . ' ' . escapeshellarg(PHP_BINARY)
        . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
        . ' 2>&1';
    $pingOutput = [];
    $pingExitCode = 0;
    exec($pingCommand, $pingOutput, $pingExitCode);
    $pingText = implode("\n", $pingOutput);

    $assertSame(2, $pingExitCode, 'Integration check must reject negative ping interval before network checks');
    $assert(strpos($pingText, 'PHPMAX_PING_INTERVAL must be greater than or equal to 0') !== false, 'Integration preflight must explain negative ping interval');
    $assert(strpos($pingText, 'secret-token') === false, 'Integration ping preflight must not print token values');

    $workDirFile = tempnam(sys_get_temp_dir(), 'phpmax-integration-workdir-test-');
    if ($workDirFile === false) {
        throw new RuntimeException('Unable to create temporary workdir fixture');
    }
    try {
        $workDirCommand = 'PHPMAX_INTEGRATION=1'
            . ' PHPMAX_TOKEN=secret-token'
            . ' PHPMAX_WORKDIR=' . escapeshellarg($workDirFile)
            . ' ' . escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(__DIR__ . '/../../tools/integration-check.php')
            . ' 2>&1';
        $workDirOutput = [];
        $workDirExitCode = 0;
        exec($workDirCommand, $workDirOutput, $workDirExitCode);
        $workDirText = implode("\n", $workDirOutput);

        $assertSame(2, $workDirExitCode, 'Integration check must reject file workdir before network checks');
        $assert(strpos($workDirText, 'Integration workdir is not a directory') !== false, 'Integration preflight must explain invalid workdir');
        $assert(strpos($workDirText, 'secret-token') === false, 'Integration workdir preflight must not print token values');
    } finally {
        @unlink($workDirFile);
    }
};
