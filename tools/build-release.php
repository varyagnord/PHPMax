<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$options = parseArguments(array_slice($argv, 1));
$output = isset($options['output']) ? absolutePath($repoRoot, (string) $options['output']) : $repoRoot . '/dist/phpmax-dev.zip';
$dryRun = !empty($options['dry-run']);
$check = !empty($options['check']);

if ($dryRun && $check) {
    fwrite(STDERR, "--dry-run and --check cannot be used together.\n");
    exit(2);
}

$spec = buildReleaseSpec($repoRoot);

if ($check) {
    validateReleaseSpec($spec);
    fwrite(STDOUT, "Release spec is valid.\n");
    exit(0);
}

if ($dryRun) {
    echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

validateReleaseSpec($spec);
buildZip($repoRoot, $output, $spec);
fwrite(STDOUT, 'Built ' . $output . PHP_EOL);

/**
 * @param list<string> $args
 * @return array<string, mixed>
 */
function parseArguments(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }
        if ($arg === '--check') {
            $options['check'] = true;
            continue;
        }
        if (strpos($arg, '--output=') === 0) {
            $options['output'] = substr($arg, strlen('--output='));
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, "Usage: php tools/build-release.php [--dry-run|--check] [--output=dist/phpmax-dev.zip]\n");
            exit(0);
        }

        fwrite(STDERR, 'Unknown argument: ' . $arg . PHP_EOL);
        exit(2);
    }

    return $options;
}

/**
 * @return array<string, mixed>
 */
function buildReleaseSpec(string $repoRoot): array
{
    $composer = json_decode((string) file_get_contents($repoRoot . '/composer.json'), true);
    if (!is_array($composer)) {
        fwrite(STDERR, "composer.json is not valid JSON.\n");
        exit(1);
    }

    $runtimePackages = runtimeComposerPackages(isset($composer['require']) && is_array($composer['require']) ? $composer['require'] : []);
    $vendorExists = is_dir($repoRoot . '/vendor');

    $files = [
        'autoload.php',
        'composer.json',
    ];
    foreach (['LICENSE', 'README.md'] as $file) {
        if (is_file($repoRoot . '/' . $file)) {
            $files[] = $file;
        }
    }

    foreach (listFiles($repoRoot . '/src/PHPMax', 'src/PHPMax') as $file) {
        $files[] = $file;
    }
    foreach (listFiles($repoRoot . '/docs/phpmax', 'docs/phpmax') as $file) {
        $files[] = $file;
    }
    if ($vendorExists) {
        foreach (listFiles($repoRoot . '/vendor', 'vendor') as $file) {
            $files[] = $file;
        }
    }

    sort($files);

    return [
        'name' => isset($composer['name']) ? (string) $composer['name'] : 'phpmax',
        'runtime_packages' => $runtimePackages,
        'vendor_included' => $vendorExists,
        'files' => array_values(array_unique($files)),
    ];
}

/**
 * @param array<string, string> $requirements
 * @return array<string, string>
 */
function runtimeComposerPackages(array $requirements): array
{
    $packages = [];
    foreach ($requirements as $name => $constraint) {
        if ($name === 'php' || strpos($name, 'ext-') === 0 || strpos($name, 'lib-') === 0) {
            continue;
        }
        $packages[$name] = (string) $constraint;
    }
    ksort($packages);

    return $packages;
}

/**
 * @param array<string, mixed> $spec
 */
function validateReleaseSpec(array $spec): void
{
    $runtimePackages = isset($spec['runtime_packages']) && is_array($spec['runtime_packages']) ? $spec['runtime_packages'] : [];
    $vendorIncluded = !empty($spec['vendor_included']);

    if ($runtimePackages !== [] && !$vendorIncluded) {
        fwrite(STDERR, "Runtime Composer packages are required, but vendor/ is missing.\n");
        fwrite(STDERR, "Run composer install --no-dev before building the shared-hosting ZIP.\n");
        exit(1);
    }
}

/**
 * @param array<string, mixed> $spec
 */
function buildZip(string $repoRoot, string $output, array $spec): void
{
    $stage = sys_get_temp_dir() . '/phpmax-release-' . getmypid() . '-' . mt_rand();
    if (!mkdir($stage, 0700, true) && !is_dir($stage)) {
        fwrite(STDERR, 'Unable to create release staging directory: ' . $stage . PHP_EOL);
        exit(1);
    }

    try {
        foreach ($spec['files'] as $relativePath) {
            if ($relativePath === 'autoload.php') {
                writeFile($stage . '/autoload.php', releaseAutoload());
                continue;
            }

            copyReleaseFile($repoRoot, $stage, (string) $relativePath);
        }

        $outputDir = dirname($output);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            fwrite(STDERR, 'Unable to create release output directory: ' . $outputDir . PHP_EOL);
            exit(1);
        }
        if (is_file($output) && !unlink($output)) {
            fwrite(STDERR, 'Unable to replace existing archive: ' . $output . PHP_EOL);
            exit(1);
        }

        if (class_exists('ZipArchive')) {
            buildWithZipArchive($stage, $output);
        } elseif (commandExists('zip')) {
            buildWithZipCommand($stage, $output);
        } else {
            fwrite(STDERR, "Neither ext-zip nor zip command is available.\n");
            exit(1);
        }
    } finally {
        removeTree($stage);
    }
}

function releaseAutoload(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PHPMax\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/PHPMax/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

PHP;
}

function copyReleaseFile(string $repoRoot, string $stage, string $relativePath): void
{
    $source = $repoRoot . '/' . $relativePath;
    if (!is_file($source)) {
        fwrite(STDERR, 'Release source file is missing: ' . $relativePath . PHP_EOL);
        exit(1);
    }

    $target = $stage . '/' . $relativePath;
    $targetDir = dirname($target);
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        fwrite(STDERR, 'Unable to create release directory: ' . $targetDir . PHP_EOL);
        exit(1);
    }
    if (!copy($source, $target)) {
        fwrite(STDERR, 'Unable to copy release file: ' . $relativePath . PHP_EOL);
        exit(1);
    }
}

/**
 * @return list<string>
 */
function listFiles(string $directory, string $prefix): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($directory) + 1));
        $files[] = $prefix . '/' . $relative;
    }
    sort($files);

    return $files;
}

function buildWithZipArchive(string $stage, string $output): void
{
    $zip = new ZipArchive();
    if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, 'Unable to create archive: ' . $output . PHP_EOL);
        exit(1);
    }

    $files = listFiles($stage, '');
    foreach ($files as $relativePath) {
        $path = $stage . '/' . ltrim($relativePath, '/');
        $zip->addFile($path, ltrim($relativePath, '/'));
    }
    $zip->close();
}

function buildWithZipCommand(string $stage, string $output): void
{
    $current = getcwd();
    if ($current === false || !chdir($stage)) {
        fwrite(STDERR, 'Unable to enter release staging directory: ' . $stage . PHP_EOL);
        exit(1);
    }

    $command = 'zip -qr ' . escapeshellarg($output) . ' .';
    $lines = [];
    $exitCode = 1;
    exec($command, $lines, $exitCode);
    chdir($current);

    if ($exitCode !== 0) {
        fwrite(STDERR, 'zip command failed with exit code ' . $exitCode . PHP_EOL);
        exit(1);
    }
}

function commandExists(string $command): bool
{
    $result = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));

    return $result !== '';
}

function writeFile(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fwrite(STDERR, 'Unable to create directory: ' . $dir . PHP_EOL);
        exit(1);
    }
    if (file_put_contents($path, $contents) === false) {
        fwrite(STDERR, 'Unable to write file: ' . $path . PHP_EOL);
        exit(1);
    }
}

function removeTree(string $path): void
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
        if ($file->isDir() && !$file->isLink()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($path);
}

function absolutePath(string $repoRoot, string $path): string
{
    if ($path === '') {
        fwrite(STDERR, "Output path cannot be empty.\n");
        exit(2);
    }
    if ($path[0] === DIRECTORY_SEPARATOR) {
        return $path;
    }

    return $repoRoot . '/' . $path;
}
