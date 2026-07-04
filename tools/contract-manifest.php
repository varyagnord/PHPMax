<?php

declare(strict_types=1);

require __DIR__ . '/../tests-php/bootstrap.php';

use PHPMax\Dispatch\EventType;
use PHPMax\Domain\Chat;
use PHPMax\Domain\ContactInfo;
use PHPMax\Domain\Element;
use PHPMax\Domain\ElementAttributes;
use PHPMax\Domain\FileRequest;
use PHPMax\Domain\Folder;
use PHPMax\Domain\FolderList;
use PHPMax\Domain\FolderUpdate;
use PHPMax\Domain\InitData;
use PHPMax\Domain\LoginConfig;
use PHPMax\Domain\LoginResponse;
use PHPMax\Domain\MaxApiError;
use PHPMax\Domain\Member;
use PHPMax\Domain\Message;
use PHPMax\Domain\Name;
use PHPMax\Domain\Presence;
use PHPMax\Domain\Profile;
use PHPMax\Domain\ReactionCounter;
use PHPMax\Domain\ReactionInfo;
use PHPMax\Domain\ReadState;
use PHPMax\Domain\Session;
use PHPMax\Domain\SyncOverrides;
use PHPMax\Domain\SyncState;
use PHPMax\Domain\User;
use PHPMax\Domain\VideoRequest;
use PHPMax\Domain\Attachments\AudioAttachment;
use PHPMax\Domain\Attachments\CallAttachment;
use PHPMax\Domain\Attachments\ContactAttachment;
use PHPMax\Domain\Attachments\ControlAttachment;
use PHPMax\Domain\Attachments\FileAttachment;
use PHPMax\Domain\Attachments\InlineKeyboardAttachment;
use PHPMax\Domain\Attachments\PhotoAttachment;
use PHPMax\Domain\Attachments\ShareAttachment;
use PHPMax\Domain\Attachments\StickerAttachment;
use PHPMax\Domain\Attachments\UnknownAttachment;
use PHPMax\Domain\Attachments\VideoAttachment;
use PHPMax\Domain\Auth\CheckCodeResponse;
use PHPMax\Domain\Auth\CheckPasswordResponse;
use PHPMax\Domain\Auth\CheckQrResponse;
use PHPMax\Domain\Auth\ConfirmRegistrationResponse;
use PHPMax\Domain\Auth\PasswordChallenge;
use PHPMax\Domain\Auth\QrStatus;
use PHPMax\Domain\Auth\RequestQrResponse;
use PHPMax\Domain\Auth\StartAuthResponse;
use PHPMax\Domain\Auth\Token;
use PHPMax\Domain\Auth\TokenAttrs;
use PHPMax\Domain\Events\FileUploadSignal;
use PHPMax\Domain\Events\MessageDeleteEvent;
use PHPMax\Domain\Events\MessageReadEvent;
use PHPMax\Domain\Events\PresenceEvent;
use PHPMax\Domain\Events\ReactionUpdateEvent;
use PHPMax\Domain\Events\TypingEvent;
use PHPMax\Domain\Events\VideoUploadSignal;
use PHPMax\Api\Account\AvatarType;
use PHPMax\Api\Account\SelfPayloadKey;
use PHPMax\Api\Auth\AuthType;
use PHPMax\Api\Auth\ProfileOptions;
use PHPMax\Api\Auth\TwoFactorAction;
use PHPMax\Api\Chats\ChatLinkPrefix;
use PHPMax\Api\Chats\ChatMemberOperation;
use PHPMax\Api\Chats\ChatOption;
use PHPMax\Api\Chats\ChatPayloadKey;
use PHPMax\Api\Chats\ControlEvent;
use PHPMax\Api\Messages\ItemType;
use PHPMax\Api\Messages\MessagePayloadKey;
use PHPMax\Api\Messages\ReadAction;
use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Users\ContactAction;
use PHPMax\Api\Users\UserPayloadKey;
use PHPMax\Domain\AccessType;
use PHPMax\Domain\AttachmentType;
use PHPMax\Domain\ChatType;
use PHPMax\Domain\MessageStatus;
use PHPMax\Domain\TranscriptionStatus;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;

$repoRoot = dirname(__DIR__);
$mode = isset($argv[1]) ? (string) $argv[1] : 'check';
$manifestPath = $repoRoot . '/docs/phpmax/contracts.json';

if (!in_array($mode, ['check', 'write'], true)) {
    fwrite(STDERR, "Usage: php tools/contract-manifest.php [check|write]\n");
    exit(2);
}

$manifest = buildManifest($repoRoot);

if ($mode === 'write') {
    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fwrite(STDERR, "Failed to encode contract manifest.\n");
        exit(1);
    }
    if (file_put_contents($manifestPath, $json . PHP_EOL) === false) {
        fwrite(STDERR, "Failed to write contract manifest: " . $manifestPath . "\n");
        exit(1);
    }
    fwrite(STDOUT, "Wrote docs/phpmax/contracts.json\n");
    exit(0);
}

if (!is_file($manifestPath)) {
    fwrite(STDERR, "Contract manifest is missing. Run: php tools/contract-manifest.php write\n");
    exit(1);
}

$stored = json_decode((string) file_get_contents($manifestPath), true);
if (!is_array($stored)) {
    fwrite(STDERR, "Contract manifest is not valid JSON: " . $manifestPath . "\n");
    exit(1);
}

$errors = [];
if ($stored !== $manifest) {
    $errors[] = 'docs/phpmax/contracts.json is out of sync with src/pymax reference.';
}

$phpCommands = phpConstants(Command::class);
$phpOpcodes = phpConstants(Opcode::class);
$phpEventTypes = phpConstants(EventType::class);
$phpDomainEnums = [
    'AccessType' => phpConstants(AccessType::class),
    'AttachmentType' => phpConstants(AttachmentType::class),
    'ChatType' => phpConstants(ChatType::class),
    'MessageStatus' => phpConstants(MessageStatus::class),
    'TranscriptionStatus' => phpConstants(TranscriptionStatus::class),
];
$phpApiEnums = [
    'auth.AuthType' => phpConstants(AuthType::class),
    'auth.ProfileOptions' => phpConstants(ProfileOptions::class),
    'auth.TwoFactorAction' => phpConstants(TwoFactorAction::class),
    'chats.ChatLinkPrefix' => phpConstants(ChatLinkPrefix::class),
    'chats.ChatMemberOperation' => phpConstants(ChatMemberOperation::class),
    'chats.ChatOption' => phpConstants(ChatOption::class),
    'chats.ChatPayloadKey' => phpConstants(ChatPayloadKey::class),
    'chats.ControlEvent' => phpConstants(ControlEvent::class),
    'messages.ItemType' => phpConstants(ItemType::class),
    'messages.MessagePayloadKey' => phpConstants(MessagePayloadKey::class),
    'messages.ReadAction' => phpConstants(ReadAction::class),
    'self.AvatarType' => phpConstants(AvatarType::class),
    'self.SelfPayloadKey' => phpConstants(SelfPayloadKey::class),
    'session.DeviceType' => phpConstants(DeviceType::class),
    'users.ContactAction' => phpConstants(ContactAction::class),
    'users.UserPayloadKey' => phpConstants(UserPayloadKey::class),
];

compareMaps($manifest['commands'], $phpCommands, 'Command', $errors);
compareMaps($manifest['opcodes'], $phpOpcodes, 'Opcode', $errors);
compareMaps($manifest['event_types'], $phpEventTypes, 'EventType', $errors);
foreach ($manifest['domain_enums'] as $name => $values) {
    compareMaps($values, $phpDomainEnums[$name] ?? [], 'Domain enum ' . $name, $errors);
}
foreach ($manifest['api_enums'] as $name => $values) {
    compareMaps($values, $phpApiEnums[$name] ?? [], 'API enum ' . $name, $errors);
}
compareEventResolverCoverage($repoRoot, array_keys($manifest['event_map']), $errors);
comparePayloadModelCoverage($manifest['payload_models'], $errors);
compareServiceMethodCoverage($manifest['service_methods'], $errors);
compareClientMethodCoverage($manifest['client_methods'], $errors);
compareServiceMethodParameterCoverage($manifest['service_method_params'], $errors);
compareClientMethodParameterCoverage($manifest['client_method_params'], $errors);
compareDomainModelCoverage($manifest['domain_models'], $errors);
compareEventModelCoverage($manifest['event_models'], $errors);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    fwrite(STDERR, "Run: php tools/contract-manifest.php write\n");
    exit(1);
}

fwrite(STDOUT, "Contract manifest is in sync.\n");

/**
 * @return array<string, mixed>
 */
function buildManifest(string $repoRoot): array
{
    $pyproject = parsePyproject($repoRoot . '/pyproject.toml');
    $pymaxVersion = isset($pyproject['version']) ? $pyproject['version'] : null;

    $manifest = [
        'generated_from' => [
            'pymax_version' => $pymaxVersion,
            'pymax_commit' => currentGitCommit($repoRoot),
            'reference_paths' => [
                'src/pymax/protocol/enums.py',
                'src/pymax/dispatch/enums.py',
                'src/pymax/dispatch/mapping.py',
                'src/pymax/api/*/enums.py',
                'src/pymax/types/domain/enums.py',
                'src/pymax/types/domain/attachments/enums.py',
                'src/pymax/api/*/payloads.py',
                'src/pymax/api/*/service.py',
                'src/pymax/infra/*.py',
                'src/pymax/types/domain/*.py',
                'src/pymax/types/domain/attachments/**/*.py',
                'src/pymax/types/events/*.py',
            ],
        ],
        'commands' => parsePythonEnum($repoRoot . '/src/pymax/protocol/enums.py', 'Command'),
        'opcodes' => parsePythonEnum($repoRoot . '/src/pymax/protocol/enums.py', 'Opcode'),
        'event_types' => parsePythonEnum($repoRoot . '/src/pymax/dispatch/enums.py', 'EventType'),
        'domain_enums' => [
            'AccessType' => parsePythonEnum($repoRoot . '/src/pymax/types/domain/enums.py', 'AccessType'),
            'AttachmentType' => parsePythonEnum($repoRoot . '/src/pymax/types/domain/attachments/enums.py', 'AttachmentType'),
            'ChatType' => parsePythonEnum($repoRoot . '/src/pymax/types/domain/enums.py', 'ChatType'),
            'MessageStatus' => parsePythonEnum($repoRoot . '/src/pymax/types/domain/enums.py', 'MessageStatus'),
            'TranscriptionStatus' => parsePythonEnum($repoRoot . '/src/pymax/types/domain/attachments/enums.py', 'TranscriptionStatus'),
        ],
        'api_enums' => [
            'auth.AuthType' => parsePythonEnum($repoRoot . '/src/pymax/api/auth/enums.py', 'AuthType'),
            'auth.ProfileOptions' => parsePythonEnum($repoRoot . '/src/pymax/api/auth/enums.py', 'ProfileOptions'),
            'auth.TwoFactorAction' => parsePythonEnum($repoRoot . '/src/pymax/api/auth/enums.py', 'TwoFactorAction'),
            'chats.ChatLinkPrefix' => parsePythonEnum($repoRoot . '/src/pymax/api/chats/enums.py', 'ChatLinkPrefix'),
            'chats.ChatMemberOperation' => parsePythonEnum($repoRoot . '/src/pymax/api/chats/enums.py', 'ChatMemberOperation'),
            'chats.ChatOption' => parsePythonEnum($repoRoot . '/src/pymax/api/chats/enums.py', 'ChatOption'),
            'chats.ChatPayloadKey' => parsePythonEnum($repoRoot . '/src/pymax/api/chats/enums.py', 'ChatPayloadKey'),
            'chats.ControlEvent' => parsePythonEnum($repoRoot . '/src/pymax/api/chats/enums.py', 'ControlEvent'),
            'messages.ItemType' => parsePythonEnum($repoRoot . '/src/pymax/api/messages/enums.py', 'ItemType'),
            'messages.MessagePayloadKey' => parsePythonEnum($repoRoot . '/src/pymax/api/messages/enums.py', 'MessagePayloadKey'),
            'messages.ReadAction' => parsePythonEnum($repoRoot . '/src/pymax/api/messages/enums.py', 'ReadAction'),
            'self.AvatarType' => parsePythonEnum($repoRoot . '/src/pymax/api/self/enums.py', 'AvatarType'),
            'self.SelfPayloadKey' => parsePythonEnum($repoRoot . '/src/pymax/api/self/enums.py', 'SelfPayloadKey'),
            'session.DeviceType' => parsePythonEnum($repoRoot . '/src/pymax/api/session/enums.py', 'DeviceType'),
            'users.ContactAction' => parsePythonEnum($repoRoot . '/src/pymax/api/users/enums.py', 'ContactAction'),
            'users.UserPayloadKey' => parsePythonEnum($repoRoot . '/src/pymax/api/users/enums.py', 'UserPayloadKey'),
        ],
        'event_map' => parsePythonEventMap($repoRoot . '/src/pymax/dispatch/mapping.py'),
        'payload_models' => parsePythonPayloadModels($repoRoot . '/src/pymax/api'),
        'service_methods' => parsePythonServiceMethods($repoRoot . '/src/pymax/api'),
        'client_methods' => parsePythonClientMethods($repoRoot . '/src/pymax/infra'),
        'service_method_params' => parsePythonServiceMethodParams($repoRoot . '/src/pymax/api'),
        'client_method_params' => parsePythonClientMethodParams($repoRoot . '/src/pymax/infra'),
        'domain_models' => parsePythonDomainModels($repoRoot . '/src/pymax/types/domain'),
        'event_models' => parsePythonEventModels($repoRoot . '/src/pymax/types/events'),
    ];

    ksort($manifest['commands']);
    ksort($manifest['opcodes']);
    ksort($manifest['event_types']);
    ksort($manifest['domain_enums']);
    foreach ($manifest['domain_enums'] as &$values) {
        ksort($values);
    }
    unset($values);
    ksort($manifest['api_enums']);
    foreach ($manifest['api_enums'] as &$values) {
        ksort($values);
    }
    unset($values);
    ksort($manifest['event_map']);
    ksort($manifest['payload_models']);
    ksort($manifest['service_methods']);
    ksort($manifest['client_methods']);
    ksort($manifest['service_method_params']);
    foreach ($manifest['service_method_params'] as &$methods) {
        ksort($methods);
    }
    unset($methods);
    ksort($manifest['client_method_params']);
    ksort($manifest['domain_models']);
    ksort($manifest['event_models']);

    return $manifest;
}

/**
 * @return array<string, string>
 */
function parsePyproject(string $path): array
{
    $result = [];
    foreach (readLines($path) as $line) {
        if (preg_match('/^([A-Za-z0-9_-]+)\s*=\s*"([^"]+)"/', trim($line), $matches)) {
            $result[$matches[1]] = $matches[2];
        }
    }

    return $result;
}

/**
 * @return array<string, int|string>
 */
function parsePythonEnum(string $path, string $className): array
{
    $result = [];
    $inside = false;
    foreach (readLines($path) as $line) {
        if (preg_match('/^class\s+' . preg_quote($className, '/') . '\b/', $line)) {
            $inside = true;
            continue;
        }
        if ($inside && preg_match('/^class\s+\w+\b/', $line)) {
            break;
        }
        if (!$inside) {
            continue;
        }
        if (preg_match('/^\s{4}([A-Z][A-Z0-9_]*)\s*=\s*(.+?)\s*$/', $line, $matches)) {
            $rawValue = trim($matches[2]);
            if (preg_match('/^-?\d+$/', $rawValue)) {
                $result[$matches[1]] = (int) $rawValue;
                continue;
            }
            if (preg_match('/^"([^"]*)"$/', $rawValue, $valueMatches)) {
                $result[$matches[1]] = $valueMatches[1];
                continue;
            }
        }
    }

    if ($result === []) {
        throw new RuntimeException('Unable to parse Python enum ' . $className . ' from ' . $path);
    }

    return $result;
}

/**
 * @return array<string, string>
 */
function parsePythonEventMap(string $path): array
{
    $result = [];
    $inside = false;
    foreach (readLines($path) as $line) {
        if (strpos($line, 'EVENT_MAP') !== false && strpos($line, '{') !== false) {
            $inside = true;
            continue;
        }
        if ($inside && preg_match('/^\}/', $line)) {
            break;
        }
        if (!$inside) {
            continue;
        }
        if (preg_match('/^\s{4}Opcode\.([A-Z0-9_]+):\s*(\w+),/', $line, $matches)) {
            $result[$matches[1]] = $matches[2];
        }
    }

    if ($result === []) {
        throw new RuntimeException('Unable to parse Python EVENT_MAP from ' . $path);
    }

    return $result;
}

/**
 * @return array<string, array<string, mixed>>
 */
function parsePythonPayloadModels(string $apiRoot): array
{
    $result = [];
    $files = glob($apiRoot . '/*/payloads.py') ?: [];
    sort($files);
    $aliasEnums = [
        'ChatOption' => parsePythonEnum($apiRoot . '/chats/enums.py', 'ChatOption'),
    ];

    foreach ($files as $file) {
        $domain = basename(dirname($file));
        $className = null;
        $fields = [];
        $payloadKeys = [];
        $lines = readLines($file);
        $lineCount = count($lines);
        for ($index = 0; $index < $lineCount; $index++) {
            $line = $lines[$index];
            if (preg_match('/^class\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $matches)) {
                if ($className !== null && $fields !== []) {
                    $result[$domain . '.' . $className] = [
                        'fields' => array_values(array_unique($fields)),
                        'payload_keys' => $payloadKeys,
                    ];
                }
                $className = $matches[1];
                $fields = [];
                $payloadKeys = [];
                continue;
            }
            if ($className !== null && preg_match('/^\s{4}([a-z_][A-Za-z0-9_]*)\s*:(.*)$/', $line, $matches)) {
                $fieldName = $matches[1];
                $expression = collectPythonFieldExpression($lines, $index, $matches[2]);
                $fields[] = $fieldName;
                $payloadKeys[$fieldName] = pythonPayloadKey($fieldName, $expression, $aliasEnums);
            }
        }
        if ($className !== null && $fields !== []) {
            $result[$domain . '.' . $className] = [
                'fields' => array_values(array_unique($fields)),
                'payload_keys' => $payloadKeys,
            ];
        }
    }

    return $result;
}

/**
 * @return array<string, array<string, mixed>>
 */
function parsePythonDomainModels(string $domainRoot): array
{
    $targets = [
        'attachments/audio.py' => ['AudioAttachment'],
        'attachments/call.py' => ['CallAttachment'],
        'attachments/contact.py' => ['ContactAttachment'],
        'attachments/control.py' => ['ControlAttachment'],
        'attachments/file.py' => ['FileAttachment', 'FileRequest'],
        'attachments/keyboards/inline.py' => ['InlineKeyboardAttachment'],
        'attachments/photo.py' => ['PhotoAttachment'],
        'attachments/share.py' => ['ShareAttachment'],
        'attachments/sticker.py' => ['StickerAttachment'],
        'attachments/unknown.py' => ['UnknownAttachment'],
        'attachments/video.py' => ['VideoAttachment', 'VideoRequest'],
        'auth.py' => [
            'CheckCodeResponse',
            'CheckPasswordResponse',
            'CheckQrResponse',
            'ConfirmRegistrationResponse',
            'PasswordChallenge',
            'QrStatus',
            'RequestQrResponse',
            'StartAuthResponse',
            'Token',
            'TokenAttrs',
        ],
        'bots.py' => ['InitData'],
        'chat.py' => ['Chat'],
        'element.py' => ['Element', 'ElementAttributes'],
        'error.py' => ['MaxApiError'],
        'folder.py' => ['Folder', 'FolderList', 'FolderUpdate'],
        'login.py' => ['LoginConfig', 'LoginResponse'],
        'member.py' => ['Member'],
        'message.py' => ['Message', 'ReactionCounter', 'ReactionInfo', 'ReadState'],
        'name.py' => ['Name'],
        'presence.py' => ['Presence'],
        'profile.py' => ['Profile'],
        'session.py' => ['Session'],
        'sync.py' => ['SyncOverrides', 'SyncState'],
        'user.py' => ['ContactInfo', 'User'],
    ];

    $result = [];
    foreach ($targets as $fileName => $classNames) {
        $path = $domainRoot . '/' . $fileName;
        foreach (parsePythonModelClasses($path, $classNames) as $className => $definition) {
            $result[$className] = $definition;
        }
    }

    return $result;
}

/**
 * @return array<string, array<string, mixed>>
 */
function parsePythonEventModels(string $eventsRoot): array
{
    $targets = [
        'file.py' => ['FileUploadSignal'],
        'mark.py' => ['MessageReadEvent'],
        'message.py' => ['MessageDeleteEvent'],
        'presence.py' => ['PresenceEvent'],
        'reaction.py' => ['ReactionUpdateEvent'],
        'typing.py' => ['TypingEvent'],
        'video.py' => ['VideoUploadSignal'],
    ];

    $result = [];
    foreach ($targets as $fileName => $classNames) {
        $path = $eventsRoot . '/' . $fileName;
        foreach (parsePythonModelClasses($path, $classNames) as $className => $definition) {
            $result[$className] = $definition;
        }
    }

    return $result;
}

/**
 * @param list<string> $classNames
 * @return array<string, array<string, mixed>>
 */
function parsePythonModelClasses(string $path, array $classNames): array
{
    $wanted = array_fill_keys($classNames, true);
    $result = [];
    $className = null;
    $useCamelAlias = true;
    $fields = [];
    $payloadKeys = [];
    $lines = readLines($path);
    $lineCount = count($lines);

    for ($index = 0; $index < $lineCount; $index++) {
        $line = $lines[$index];
        if (preg_match('/^class\s+([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $matches)) {
            if ($className !== null && $fields !== []) {
                $result[$className] = [
                    'fields' => array_values(array_unique($fields)),
                    'payload_keys' => $payloadKeys,
                ];
            }
            $className = isset($wanted[$matches[1]]) ? $matches[1] : null;
            $useCamelAlias = strpos($line, 'CamelModel') !== false;
            $fields = [];
            $payloadKeys = [];
            continue;
        }
        if ($className !== null && preg_match('/^\s{4}([a-z][A-Za-z0-9_]*)\s*:(.*)$/', $line, $matches)) {
            $fieldName = $matches[1];
            if (strpos($fieldName, '_') === 0) {
                continue;
            }
            $expression = collectPythonFieldExpression($lines, $index, $matches[2]);
            $fields[] = $fieldName;
            $payloadKeys[$fieldName] = pythonPayloadKey($fieldName, $expression, [], $useCamelAlias);
        }
    }
    if ($className !== null && $fields !== []) {
        $result[$className] = [
            'fields' => array_values(array_unique($fields)),
            'payload_keys' => $payloadKeys,
        ];
    }

    foreach ($classNames as $wantedClass) {
        if (!isset($result[$wantedClass])) {
            throw new RuntimeException('Unable to parse Python domain model ' . $wantedClass . ' from ' . $path);
        }
    }

    return $result;
}

/**
 * @return array<string, array<string, string>>
 */
function parsePythonServiceMethods(string $apiRoot): array
{
    $result = [];
    $files = glob($apiRoot . '/*/service.py') ?: [];
    sort($files);

    foreach ($files as $file) {
        $domain = basename(dirname($file));
        $methods = [];
        foreach (parsePythonPublicMethods($file) as $pythonMethod => $definition) {
            $methods[$pythonMethod] = phpServiceMethodName($domain, $pythonMethod);
        }
        ksort($methods);
        $result[$domain] = $methods;
    }

    return $result;
}

/**
 * @return array<string, array<string, list<string>>>
 */
function parsePythonServiceMethodParams(string $apiRoot): array
{
    $result = [];
    $files = glob($apiRoot . '/*/service.py') ?: [];
    sort($files);

    foreach ($files as $file) {
        $domain = basename(dirname($file));
        $methods = [];
        foreach (parsePythonPublicMethods($file) as $pythonMethod => $definition) {
            $methods[$pythonMethod] = [
                'php_method' => phpServiceMethodName($domain, $pythonMethod),
                'params' => $definition['params'],
                'php_params' => pythonParamsToPhpParams($definition['params']),
            ];
        }
        ksort($methods);
        $result[$domain] = $methods;
    }

    return $result;
}

function phpServiceMethodName(string $domain, string $pythonMethod): string
{
    $overrides = [
        'auth.check_2fa' => 'checkTwoFactor',
        'auth.remove_2fa' => 'removeTwoFactor',
        'auth.set_2fa' => 'setTwoFactor',
    ];
    $key = $domain . '.' . $pythonMethod;

    return isset($overrides[$key]) ? $overrides[$key] : pythonSnakeToCamel($pythonMethod);
}

/**
 * @return array<string, string>
 */
function parsePythonClientMethods(string $infraRoot): array
{
    $result = [];
    $files = glob($infraRoot . '/*.py') ?: [];
    sort($files);
    $skip = ['__init__.py' => true, 'base.py' => true, 'protocol.py' => true];

    foreach ($files as $file) {
        if (isset($skip[basename($file)])) {
            continue;
        }
        foreach (parsePythonPublicMethods($file) as $pythonMethod => $definition) {
            $result[$pythonMethod] = phpClientMethodName($pythonMethod);
        }
    }
    ksort($result);

    return $result;
}

/**
 * @return array<string, array{php_method: string, params: list<string>, php_params: list<string>}>
 */
function parsePythonClientMethodParams(string $infraRoot): array
{
    $result = [];
    $files = glob($infraRoot . '/*.py') ?: [];
    sort($files);
    $skip = ['__init__.py' => true, 'base.py' => true, 'protocol.py' => true];

    foreach ($files as $file) {
        if (isset($skip[basename($file)])) {
            continue;
        }
        foreach (parsePythonPublicMethods($file) as $pythonMethod => $definition) {
            $result[$pythonMethod] = [
                'php_method' => phpClientMethodName($pythonMethod),
                'params' => $definition['params'],
                'php_params' => pythonParamsToPhpParams($definition['params']),
            ];
        }
    }
    ksort($result);

    return $result;
}

function phpClientMethodName(string $pythonMethod): string
{
    $overrides = [
        'check_2fa' => 'checkTwoFactor',
        'remove_2fa' => 'removeTwoFactor',
        'set_2fa' => 'setTwoFactor',
    ];

    return isset($overrides[$pythonMethod]) ? $overrides[$pythonMethod] : pythonSnakeToCamel($pythonMethod);
}

/**
 * @return array<string, array{params: list<string>}>
 */
function parsePythonPublicMethods(string $path): array
{
    $result = [];
    $lines = readLines($path);
    $lineCount = count($lines);

    for ($index = 0; $index < $lineCount; $index++) {
        $line = $lines[$index];
        if (!preg_match('/^\s{4}(?:async\s+)?def\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $line, $matches)) {
            continue;
        }
        $pythonMethod = $matches[1];
        if (strpos($pythonMethod, '_') === 0) {
            continue;
        }

        $signature = trim($line);
        $balance = pythonExpressionBalance($signature);
        while ($balance > 0 && isset($lines[$index + 1])) {
            $index++;
            $signature .= ' ' . trim($lines[$index]);
            $balance += pythonExpressionBalance(trim($lines[$index]));
        }

        $result[$pythonMethod] = [
            'params' => parsePythonSignatureParams($signature),
        ];
    }

    return $result;
}

/**
 * @return list<string>
 */
function parsePythonSignatureParams(string $signature): array
{
    $start = strpos($signature, '(');
    if ($start === false) {
        return [];
    }
    $end = findMatchingParen($signature, $start);
    if ($end === null) {
        return [];
    }

    $inside = substr($signature, $start + 1, $end - $start - 1);
    $params = [];
    foreach (splitTopLevelComma($inside) as $rawParam) {
        $rawParam = trim($rawParam);
        if ($rawParam === '' || $rawParam === '*' || strpos($rawParam, '**') === 0) {
            continue;
        }
        if (strpos($rawParam, '*') === 0) {
            $rawParam = ltrim($rawParam, '*');
        }
        $name = preg_split('/[:=]/', $rawParam, 2)[0] ?? '';
        $name = trim($name);
        if ($name === '' || $name === 'self' || $name === 'cls' || $name === '_') {
            continue;
        }
        $params[] = $name;
    }

    return $params;
}

function findMatchingParen(string $value, int $start): ?int
{
    $depth = 0;
    $length = strlen($value);
    for ($offset = $start; $offset < $length; $offset++) {
        $char = $value[$offset];
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return $offset;
            }
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function splitTopLevelComma(string $value): array
{
    $parts = [];
    $current = '';
    $depth = 0;
    $length = strlen($value);
    for ($offset = 0; $offset < $length; $offset++) {
        $char = $value[$offset];
        if ($char === '(' || $char === '[' || $char === '{') {
            $depth++;
        } elseif ($char === ')' || $char === ']' || $char === '}') {
            $depth--;
        }
        if ($char === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $char;
    }
    if (trim($current) !== '') {
        $parts[] = trim($current);
    }

    return $parts;
}

/**
 * @param list<string> $params
 * @return list<string>
 */
function pythonParamsToPhpParams(array $params): array
{
    return array_map(static function (string $param): string {
        return pythonSnakeToCamel($param);
    }, $params);
}

/**
 * @param list<string> $lines
 */
function collectPythonFieldExpression(array $lines, int &$index, string $rest): string
{
    $equalsPosition = strpos($rest, '=');
    if ($equalsPosition === false) {
        return '';
    }

    $expression = trim(substr($rest, $equalsPosition + 1));
    $balance = pythonExpressionBalance($expression);
    while ($balance > 0 && isset($lines[$index + 1])) {
        $nextLine = $lines[$index + 1];
        if (!preg_match('/^\s{8,}\S|^\s*$/', $nextLine)) {
            break;
        }
        $index++;
        $expression .= ' ' . trim($nextLine);
        $balance += pythonExpressionBalance(trim($nextLine));
    }

    return $expression;
}

function pythonExpressionBalance(string $expression): int
{
    return substr_count($expression, '(') + substr_count($expression, '[') + substr_count($expression, '{')
        - substr_count($expression, ')') - substr_count($expression, ']') - substr_count($expression, '}');
}

/**
 * @param array<string, array<string, int|string>> $aliasEnums
 */
function pythonPayloadKey(string $fieldName, string $expression, array $aliasEnums, bool $useCamelAlias = true): string
{
    foreach (['serialization_alias', 'alias'] as $aliasArgument) {
        if (preg_match('/' . $aliasArgument . '\s*=\s*"([^"]+)"/', $expression, $matches)) {
            return $matches[1];
        }
        if (preg_match('/' . $aliasArgument . '\s*=\s*([A-Za-z_][A-Za-z0-9_]*)\.([A-Z][A-Z0-9_]*)\.value/', $expression, $matches)) {
            $enumName = $matches[1];
            $constantName = $matches[2];
            if (isset($aliasEnums[$enumName][$constantName])) {
                return (string) $aliasEnums[$enumName][$constantName];
            }
        }
    }

    return $useCamelAlias ? pythonSnakeToCamel($fieldName) : $fieldName;
}

function pythonSnakeToCamel(string $fieldName): string
{
    $fieldName = rtrim($fieldName, '_');

    return preg_replace_callback('/_([a-z])/', static function (array $matches): string {
        return strtoupper($matches[1]);
    }, $fieldName) ?? $fieldName;
}

/**
 * @return array<string, int|string>
 */
function phpConstants(string $className): array
{
    $reflection = new ReflectionClass($className);
    $constants = $reflection->getConstants();
    ksort($constants);

    return $constants;
}

/**
 * @param array<string, int|string> $expected
 * @param array<string, int|string> $actual
 * @param list<string> $errors
 */
function compareMaps(array $expected, array $actual, string $label, array &$errors): void
{
    if ($expected === $actual) {
        return;
    }

    foreach ($expected as $name => $value) {
        if (!array_key_exists($name, $actual)) {
            $errors[] = $label . ' missing in PHP: ' . $name;
        } elseif ($actual[$name] !== $value) {
            $errors[] = $label . ' value mismatch for ' . $name . ': expected ' . var_export($value, true) . ', got ' . var_export($actual[$name], true);
        }
    }
    foreach ($actual as $name => $value) {
        if (!array_key_exists($name, $expected)) {
            $errors[] = $label . ' extra in PHP: ' . $name;
        }
    }
}

/**
 * @param list<string> $referenceOpcodes
 * @param list<string> $errors
 */
function compareEventResolverCoverage(string $repoRoot, array $referenceOpcodes, array &$errors): void
{
    $path = $repoRoot . '/src/PHPMax/Dispatch/EventResolver.php';
    $contents = (string) file_get_contents($path);
    preg_match_all('/Opcode::([A-Z0-9_]+)/', $contents, $matches);
    $phpOpcodes = array_values(array_unique($matches[1]));
    sort($phpOpcodes);
    sort($referenceOpcodes);

    foreach ($referenceOpcodes as $opcode) {
        if (!in_array($opcode, $phpOpcodes, true)) {
            $errors[] = 'EventResolver does not cover PyMax event opcode: ' . $opcode;
        }
    }
    foreach ($phpOpcodes as $opcode) {
        if (!in_array($opcode, $referenceOpcodes, true)) {
            $errors[] = 'EventResolver covers opcode not present in PyMax EVENT_MAP: ' . $opcode;
        }
    }
}

/**
 * @param array<string, array<string, mixed>> $payloadModels
 * @param list<string> $errors
 */
function comparePayloadModelCoverage(array $payloadModels, array &$errors): void
{
    foreach ($payloadModels as $referenceName => $definition) {
        $className = phpPayloadClassName($referenceName);
        if ($className === null || !class_exists($className)) {
            $errors[] = 'Payload model missing in PHP: ' . $referenceName;
            continue;
        }

        $expectedKeys = [];
        if (isset($definition['payload_keys']) && is_array($definition['payload_keys'])) {
            $expectedKeys = array_values($definition['payload_keys']);
        }
        $actualKeys = phpPayloadKeys($className);
        sort($expectedKeys);
        sort($actualKeys);
        if ($expectedKeys === $actualKeys) {
            continue;
        }

        foreach ($expectedKeys as $key) {
            if (!in_array($key, $actualKeys, true)) {
                $errors[] = 'Payload model ' . $referenceName . ' missing PHP serialized key: ' . $key;
            }
        }
        foreach ($actualKeys as $key) {
            if (!in_array($key, $expectedKeys, true)) {
                $errors[] = 'Payload model ' . $referenceName . ' has extra PHP serialized key: ' . $key;
            }
        }
    }
}

/**
 * @param array<string, array<string, string>> $serviceMethods
 * @param list<string> $errors
 */
function compareServiceMethodCoverage(array $serviceMethods, array &$errors): void
{
    $classes = phpServiceClasses();

    foreach ($serviceMethods as $domain => $methods) {
        if (!isset($classes[$domain]) || !class_exists($classes[$domain])) {
            $errors[] = 'Service missing in PHP for PyMax api domain: ' . $domain;
            continue;
        }
        foreach ($methods as $pythonMethod => $phpMethod) {
            if (!method_exists($classes[$domain], $phpMethod)) {
                $errors[] = 'Service method missing in PHP: ' . $domain . '.' . $pythonMethod . ' -> ' . $phpMethod;
            }
        }
    }
}

/**
 * @param array<string, string> $clientMethods
 * @param list<string> $errors
 */
function compareClientMethodCoverage(array $clientMethods, array &$errors): void
{
    foreach ($clientMethods as $pythonMethod => $phpMethod) {
        if (!method_exists('PHPMax\\Client', $phpMethod)) {
            $errors[] = 'Client method missing in PHP: ' . $pythonMethod . ' -> ' . $phpMethod;
        }
    }
}

/**
 * @return array<string, string>
 */
function phpServiceClasses(): array
{
    return [
        'auth' => 'PHPMax\\Api\\Auth\\AuthService',
        'bots' => 'PHPMax\\Api\\Bots\\BotsService',
        'chats' => 'PHPMax\\Api\\Chats\\ChatService',
        'messages' => 'PHPMax\\Api\\Messages\\MessageService',
        'self' => 'PHPMax\\Api\\Account\\AccountService',
        'session' => 'PHPMax\\Api\\Session\\SessionService',
        'uploads' => 'PHPMax\\Api\\Uploads\\UploadService',
        'users' => 'PHPMax\\Api\\Users\\UserService',
    ];
}

/**
 * @param array<string, array<string, array<string, mixed>>> $serviceMethodParams
 * @param list<string> $errors
 */
function compareServiceMethodParameterCoverage(array $serviceMethodParams, array &$errors): void
{
    $classes = phpServiceClasses();

    foreach ($serviceMethodParams as $domain => $methods) {
        if (!isset($classes[$domain]) || !class_exists($classes[$domain])) {
            $errors[] = 'Service missing in PHP for PyMax api domain: ' . $domain;
            continue;
        }
        foreach ($methods as $pythonMethod => $definition) {
            $phpMethod = isset($definition['php_method']) ? (string) $definition['php_method'] : phpServiceMethodName($domain, $pythonMethod);
            if (!method_exists($classes[$domain], $phpMethod)) {
                continue;
            }
            $expected = isset($definition['php_params']) && is_array($definition['php_params'])
                ? array_values($definition['php_params'])
                : [];
            $actual = phpMethodParameterNames($classes[$domain], $phpMethod);
            if ($expected !== $actual) {
                $errors[] = 'Service method parameter mismatch: ' . $domain . '.' . $pythonMethod
                    . ' -> ' . $phpMethod . ' expected [' . implode(', ', $expected) . '] got [' . implode(', ', $actual) . ']';
            }
        }
    }
}

/**
 * @param array<string, array<string, mixed>> $clientMethodParams
 * @param list<string> $errors
 */
function compareClientMethodParameterCoverage(array $clientMethodParams, array &$errors): void
{
    foreach ($clientMethodParams as $pythonMethod => $definition) {
        $phpMethod = isset($definition['php_method']) ? (string) $definition['php_method'] : phpClientMethodName($pythonMethod);
        if (!method_exists('PHPMax\\Client', $phpMethod)) {
            continue;
        }
        $expected = isset($definition['php_params']) && is_array($definition['php_params'])
            ? array_values($definition['php_params'])
            : [];
        $actual = phpMethodParameterNames('PHPMax\\Client', $phpMethod);
        if ($expected !== $actual) {
            $errors[] = 'Client method parameter mismatch: ' . $pythonMethod
                . ' -> ' . $phpMethod . ' expected [' . implode(', ', $expected) . '] got [' . implode(', ', $actual) . ']';
        }
    }
}

/**
 * @return list<string>
 */
function phpMethodParameterNames(string $className, string $method): array
{
    $reflection = new ReflectionMethod($className, $method);
    $params = [];
    foreach ($reflection->getParameters() as $parameter) {
        $params[] = $parameter->getName();
    }

    return $params;
}

/**
 * @param array<string, array<string, mixed>> $domainModels
 * @param list<string> $errors
 */
function compareDomainModelCoverage(array $domainModels, array &$errors): void
{
    $classes = [
        'AudioAttachment' => AudioAttachment::class,
        'CallAttachment' => CallAttachment::class,
        'Chat' => Chat::class,
        'CheckCodeResponse' => CheckCodeResponse::class,
        'CheckPasswordResponse' => CheckPasswordResponse::class,
        'CheckQrResponse' => CheckQrResponse::class,
        'ConfirmRegistrationResponse' => ConfirmRegistrationResponse::class,
        'ContactAttachment' => ContactAttachment::class,
        'ContactInfo' => ContactInfo::class,
        'ControlAttachment' => ControlAttachment::class,
        'Element' => Element::class,
        'ElementAttributes' => ElementAttributes::class,
        'FileAttachment' => FileAttachment::class,
        'FileRequest' => FileRequest::class,
        'Folder' => Folder::class,
        'FolderList' => FolderList::class,
        'FolderUpdate' => FolderUpdate::class,
        'InlineKeyboardAttachment' => InlineKeyboardAttachment::class,
        'InitData' => InitData::class,
        'LoginConfig' => LoginConfig::class,
        'LoginResponse' => LoginResponse::class,
        'MaxApiError' => MaxApiError::class,
        'Member' => Member::class,
        'Message' => Message::class,
        'Name' => Name::class,
        'PasswordChallenge' => PasswordChallenge::class,
        'PhotoAttachment' => PhotoAttachment::class,
        'Presence' => Presence::class,
        'Profile' => Profile::class,
        'QrStatus' => QrStatus::class,
        'ReactionCounter' => ReactionCounter::class,
        'ReactionInfo' => ReactionInfo::class,
        'ReadState' => ReadState::class,
        'RequestQrResponse' => RequestQrResponse::class,
        'Session' => Session::class,
        'ShareAttachment' => ShareAttachment::class,
        'StartAuthResponse' => StartAuthResponse::class,
        'StickerAttachment' => StickerAttachment::class,
        'SyncOverrides' => SyncOverrides::class,
        'SyncState' => SyncState::class,
        'Token' => Token::class,
        'TokenAttrs' => TokenAttrs::class,
        'UnknownAttachment' => UnknownAttachment::class,
        'User' => User::class,
        'VideoAttachment' => VideoAttachment::class,
        'VideoRequest' => VideoRequest::class,
    ];

    foreach ($domainModels as $name => $definition) {
        if (!isset($classes[$name])) {
            $errors[] = 'Domain model has no PHP class mapping: ' . $name;
            continue;
        }
        $expectedKeys = [];
        if (isset($definition['payload_keys']) && is_array($definition['payload_keys'])) {
            $expectedKeys = array_values($definition['payload_keys']);
        }
        $actualKeys = phpPayloadKeys($classes[$name]);
        sort($expectedKeys);
        sort($actualKeys);
        if ($expectedKeys === $actualKeys) {
            continue;
        }

        foreach ($expectedKeys as $key) {
            if (!in_array($key, $actualKeys, true)) {
                $errors[] = 'Domain model ' . $name . ' missing PHP serialized key: ' . $key;
            }
        }
        foreach ($actualKeys as $key) {
            if (!in_array($key, $expectedKeys, true)) {
                $errors[] = 'Domain model ' . $name . ' has extra PHP serialized key: ' . $key;
            }
        }
    }
}

/**
 * @param array<string, array<string, mixed>> $eventModels
 * @param list<string> $errors
 */
function compareEventModelCoverage(array $eventModels, array &$errors): void
{
    $classes = [
        'FileUploadSignal' => FileUploadSignal::class,
        'MessageDeleteEvent' => MessageDeleteEvent::class,
        'MessageReadEvent' => MessageReadEvent::class,
        'PresenceEvent' => PresenceEvent::class,
        'ReactionUpdateEvent' => ReactionUpdateEvent::class,
        'TypingEvent' => TypingEvent::class,
        'VideoUploadSignal' => VideoUploadSignal::class,
    ];

    foreach ($eventModels as $name => $definition) {
        if (!isset($classes[$name])) {
            $errors[] = 'Event model has no PHP class mapping: ' . $name;
            continue;
        }
        $expectedKeys = [];
        if (isset($definition['payload_keys']) && is_array($definition['payload_keys'])) {
            $expectedKeys = array_values($definition['payload_keys']);
        }
        $actualKeys = phpPayloadKeys($classes[$name]);
        sort($expectedKeys);
        sort($actualKeys);
        if ($expectedKeys === $actualKeys) {
            continue;
        }

        foreach ($expectedKeys as $key) {
            if (!in_array($key, $actualKeys, true)) {
                $errors[] = 'Event model ' . $name . ' missing PHP serialized key: ' . $key;
            }
        }
        foreach ($actualKeys as $key) {
            if (!in_array($key, $expectedKeys, true)) {
                $errors[] = 'Event model ' . $name . ' has extra PHP serialized key: ' . $key;
            }
        }
    }
}

function phpPayloadClassName(string $referenceName): ?string
{
    $parts = explode('.', $referenceName, 2);
    if (count($parts) !== 2) {
        return null;
    }

    $domain = $parts[0];
    $className = $parts[1];
    $namespaces = [
        'auth' => 'PHPMax\\Api\\Auth\\',
        'bots' => 'PHPMax\\Api\\Bots\\',
        'chats' => 'PHPMax\\Api\\Chats\\',
        'messages' => 'PHPMax\\Api\\Messages\\',
        'self' => 'PHPMax\\Api\\Account\\',
        'session' => 'PHPMax\\Api\\Session\\',
        'uploads' => 'PHPMax\\Api\\Uploads\\',
        'users' => 'PHPMax\\Api\\Users\\',
    ];
    if ($domain === 'users' && $className === '_ContactPayload') {
        $className = 'ContactPayload';
    }
    if (!isset($namespaces[$domain])) {
        return null;
    }

    return $namespaces[$domain] . $className;
}

/**
 * @return list<string>
 */
function phpPayloadKeys(string $className): array
{
    $method = new ReflectionMethod($className, 'schema');
    $method->setAccessible(true);
    $schema = $method->invoke(null);
    if (!is_array($schema)) {
        return [];
    }

    $keys = [];
    foreach ($schema as $property => $definition) {
        if (is_array($definition) && isset($definition['payload'])) {
            $keys[] = (string) $definition['payload'];
            continue;
        }
        $keys[] = (string) $property;
    }

    return array_values(array_unique($keys));
}

/**
 * @return list<string>
 */
function readLines(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read file: ' . $path);
    }

    return $lines;
}

function currentGitCommit(string $repoRoot): ?string
{
    $current = getcwd();
    if ($current === false || !chdir($repoRoot)) {
        return null;
    }

    $output = [];
    $exitCode = 1;
    exec('git rev-parse HEAD 2>/dev/null', $output, $exitCode);
    chdir($current);

    return $exitCode === 0 && isset($output[0]) ? trim($output[0]) : null;
}
