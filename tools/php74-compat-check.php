<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$args = array_slice($argv, 1);

if ($args === ['--help'] || $args === ['-h']) {
    fwrite(STDOUT, "Usage: php tools/php74-compat-check.php [path ...]\n");
    exit(0);
}

$paths = $args !== [] ? $args : ['src/PHPMax', 'tests-php', 'tools'];
$files = collectPhpFiles($repoRoot, $paths);
$errors = [];

foreach ($files as $file) {
    $errors = array_merge($errors, scanPhp74Compatibility($file));
}

if ($errors !== []) {
    fwrite(STDERR, "PHP 7.4 compatibility check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, ' - ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "PHP 7.4 compatibility check passed.\n");

/**
 * @param list<string> $paths
 * @return list<string>
 */
function collectPhpFiles(string $repoRoot, array $paths): array
{
    $files = [];
    foreach ($paths as $path) {
        $absolute = $path !== '' && $path[0] === '/' ? $path : $repoRoot . '/' . $path;
        if (is_file($absolute)) {
            if (substr($absolute, -4) === '.php') {
                $files[] = $absolute;
            }
            continue;
        }
        if (!is_dir($absolute)) {
            throw new RuntimeException('Path does not exist: ' . $path);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

/**
 * @return list<string>
 */
function scanPhp74Compatibility(string $file): array
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('Unable to read PHP file: ' . $file);
    }

    $code = stripPhpCommentsAndStrings($source);
    $issues = [];

    addPatternIssue($issues, $file, $source, '/^[ \t]*#\[/m', 'PHP attributes require PHP 8.0+');
    addPatternIssue($issues, $file, $code, '/(^|\n)\s*enum\s+[A-Za-z_][A-Za-z0-9_]*/m', 'native enum declarations require PHP 8.1+');
    addPatternIssue($issues, $file, $code, '/\bmatch\s*\(/', 'match expressions require PHP 8.0+');
    addPatternIssue($issues, $file, $code, '/\?->/', 'nullsafe operator requires PHP 8.0+');
    addPatternIssue($issues, $file, $code, '/\breadonly\s+(?:class|public|protected|private|\$)/', 'readonly classes/properties require PHP 8.1+');
    addPatternIssue($issues, $file, $code, '/\b(?:str_contains|str_starts_with|str_ends_with|get_debug_type|enum_exists)\s*\(/', 'called function is not available in PHP 7.4');

    addPatternIssue(
        $issues,
        $file,
        $code,
        '/function\s+__construct\s*\([^)]*\b(?:public|protected|private)\s+(?:readonly\s+)?(?:\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s+)?\$\w+/s',
        'constructor property promotion requires PHP 8.0+'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/function\s*(?:[A-Za-z_][A-Za-z0-9_]*)?\s*\([^)]*\)\s*:\s*\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\s*\|\s*\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)+/s',
        'union return types require PHP 8.0+'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/(?:^|[,(])\s*\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\s*\|\s*\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)+\s*(?:&\s*)?\$\w+/m',
        'union parameter types require PHP 8.0+'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/\b(?:public|protected|private|var)\s+(?:static\s+)?\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*(?:\s*\|\s*\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)+\s+\$\w+/',
        'union property types require PHP 8.0+'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/(?:^|[,(])\s*(?:mixed|never)\s+(?:&\s*)?\$\w+/m',
        'mixed/never parameter types require PHP 8.0+'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/function\s*(?:[A-Za-z_][A-Za-z0-9_]*)?\s*\([^)]*\)\s*:\s*(?:mixed|never|static|false|true|null)\b/s',
        'this return type is not available in PHP 7.4'
    );
    addPatternIssue(
        $issues,
        $file,
        $code,
        '/\b(?:public|protected|private|var)\s+(?:static\s+)?(?:mixed|never|false|true|null)\s+\$\w+/',
        'this property type is not available in PHP 7.4'
    );

    return $issues;
}

function stripPhpCommentsAndStrings(string $source): string
{
    $tokens = token_get_all($source);
    $result = '';
    foreach ($tokens as $token) {
        if (!is_array($token)) {
            $result .= $token;
            continue;
        }

        $id = $token[0];
        $text = $token[1];
        if ($id === T_COMMENT || $id === T_DOC_COMMENT || $id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) {
            $result .= blankTokenText($text);
            continue;
        }
        if (defined('T_INLINE_HTML') && $id === T_INLINE_HTML) {
            $result .= blankTokenText($text);
            continue;
        }

        $result .= $text;
    }

    return $result;
}

function blankTokenText(string $text): string
{
    $blank = preg_replace('/[^\r\n]/', ' ', $text);

    return $blank === null ? '' : $blank;
}

/**
 * @param list<string> $issues
 */
function addPatternIssue(array &$issues, string $file, string $source, string $pattern, string $message): void
{
    if (preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        return;
    }

    $offset = (int) $matches[0][1];
    $issues[] = relativePath($file) . ':' . lineForOffset($source, $offset) . ': ' . $message;
}

function lineForOffset(string $source, int $offset): int
{
    if ($offset <= 0) {
        return 1;
    }

    return substr_count(substr($source, 0, $offset), "\n") + 1;
}

function relativePath(string $file): string
{
    $root = dirname(__DIR__) . '/';
    if (strpos($file, $root) === 0) {
        return substr($file, strlen($root));
    }

    return $file;
}
