<?php

declare(strict_types=1);

final class Php74CompatCheckTestHelper
{
    /**
     * @return array{0: int, 1: string}
     */
    public static function run(string $tool, string $path): array
    {
        $output = [];
        $exitCode = 1;
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tool) . ' ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);

        return [$exitCode, implode("\n", $output)];
    }

    public static function removeTree(string $path): void
    {
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
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    if (!function_exists('exec')) {
        $assert(true, 'php74 compatibility checker test requires exec');
        return;
    }

    $root = dirname(__DIR__, 2);
    $tool = $root . '/tools/php74-compat-check.php';
    $dir = sys_get_temp_dir() . '/phpmax-php74-compat-' . getmypid() . '-' . mt_rand();
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create PHP 7.4 compatibility fixture dir');
    }

    try {
        $good = $dir . '/good.php';
        file_put_contents($good, "<?php\nclass GoodPhp74 { public function method(?string \$name): array { return []; } }\n");
        list($goodExit, $goodOutput) = Php74CompatCheckTestHelper::run($tool, $good);
        $assertSame(0, $goodExit, 'PHP 7.4-compatible fixture must pass: ' . $goodOutput);

        $bad = $dir . '/bad.php';
        $badSource = "<?php\n"
            . '#' . "[Attr]\n"
            . "enum BadEnum: string { case A = 'a'; }\n"
            . "class BadCtor { public function __construct(public string \$name) {} }\n"
            . "function badUnion(int|string \$value): mixed { return match(\$value) { default => null }; }\n"
            . "\$object?->method();\n"
            . "str_contains('abc', 'a');\n";
        file_put_contents($bad, $badSource);
        list($badExit, $badOutput) = Php74CompatCheckTestHelper::run($tool, $bad);
        $assertSame(1, $badExit, 'PHP 8+ fixture must fail compatibility check');
        foreach ([
            'PHP attributes require PHP 8.0+',
            'native enum declarations require PHP 8.1+',
            'constructor property promotion requires PHP 8.0+',
            'union parameter types require PHP 8.0+',
            'this return type is not available in PHP 7.4',
            'match expressions require PHP 8.0+',
            'nullsafe operator requires PHP 8.0+',
            'called function is not available in PHP 7.4',
        ] as $expected) {
            $assert(strpos($badOutput, $expected) !== false, 'Compatibility checker output must include: ' . $expected);
        }
    } finally {
        Php74CompatCheckTestHelper::removeTree($dir);
    }
};
