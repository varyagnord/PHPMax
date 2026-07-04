<?php

declare(strict_types=1);

require __DIR__ . '/../tests-php/bootstrap.php';

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/../tests-php', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    if (substr($file->getFilename(), -8) !== 'Test.php') {
        continue;
    }
    $files[] = $file->getPathname();
}

sort($files);

$failures = 0;
$assertions = 0;

$assert = static function ($condition, string $message = 'Assertion failed') use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertSame = static function ($expected, $actual, string $message = '') use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        $details = 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
        throw new RuntimeException($message !== '' ? $message . ': ' . $details : $details);
    }
};

$assertThrows = static function (string $exceptionClass, callable $callback, string $message = '') use (&$assertions): void {
    $assertions++;
    try {
        $callback();
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            return;
        }
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '') .
            'Expected ' . $exceptionClass . ', got ' . get_class($e) . ' with message: ' . $e->getMessage()
        );
    }

    throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Expected ' . $exceptionClass . ' to be thrown');
};

foreach ($files as $file) {
    try {
        $test = require $file;
        if (!is_callable($test)) {
            throw new RuntimeException('Test file must return a callable');
        }
        $test($assert, $assertSame, $assertThrows);
        fwrite(STDOUT, '.');
    } catch (Throwable $e) {
        $failures++;
        fwrite(STDOUT, "F\n");
        fwrite(STDERR, $file . "\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    }
}

fwrite(STDOUT, "\nAssertions: " . $assertions . "\n");

if ($failures > 0) {
    fwrite(STDERR, "Failures: " . $failures . "\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");

