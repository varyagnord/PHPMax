<?php

declare(strict_types=1);

namespace PHPMax\Config;

use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Api\Uploads\HttpUploaderInterface;
use PHPMax\Auth\AuthFlowInterface;
use PHPMax\Domain\SyncOverrides;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Session\SessionStoreInterface;

class ClientOptions
{
    /** @var string|null */
    public $phone;
    /** @var string */
    public $workDir;
    /** @var string */
    public $sessionName;
    /** @var string|null */
    public $token;
    /** @var string|null */
    public $registrationFirstName;
    /** @var string|null */
    public $registrationLastName;
    /** @var string */
    public $host;
    /** @var int */
    public $port;
    /** @var string */
    public $wsUrl;
    /** @var string|null */
    public $proxy;
    /** @var bool */
    public $useSsl;
    /** @var float */
    public $requestTimeout;
    /** @var float */
    public $pingInterval;
    /** @var float */
    public $connectTimeout;
    /** @var bool */
    public $telemetry;
    /** @var bool */
    public $reconnect;
    /** @var float */
    public $reconnectDelay;
    /** @var float */
    public $qrPollTimeout;
    /** @var float */
    public $executionSafetyMargin;
    /** @var string */
    public $deviceId;
    /** @var string */
    public $mtInstanceId;
    /** @var int */
    public $clientSessionId;
    /** @var MobileUserAgentPayload */
    public $userAgent;
    /** @var SyncOverrides */
    public $sync;
    /** @var SessionStoreInterface|null */
    public $store;
    /** @var AuthFlowInterface|null */
    public $authFlow;
    /** @var HttpUploaderInterface|null */
    public $httpUploader;
    /** @var int */
    public $uploadChunkSize;
    /** @var float */
    public $uploadProcessingTimeout;
    /** @var float */
    public $uploadHttpTimeout;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->phone = isset($options['phone']) ? (string) $options['phone'] : null;
        $this->workDir = isset($options['workDir']) ? (string) $options['workDir'] : '.';
        $this->sessionName = isset($options['sessionName']) ? (string) $options['sessionName'] : 'session.json';
        $this->token = isset($options['token']) ? (string) $options['token'] : null;
        $this->registrationFirstName = isset($options['registrationFirstName']) ? (string) $options['registrationFirstName'] : null;
        $this->registrationLastName = isset($options['registrationLastName']) ? (string) $options['registrationLastName'] : null;
        $this->host = isset($options['host']) ? (string) $options['host'] : 'api.oneme.ru';
        $this->assertNonEmptyString($this->host, 'host');
        $this->port = isset($options['port']) ? (int) $options['port'] : 443;
        $this->assertPort($this->port, 'port');
        $this->wsUrl = isset($options['wsUrl']) ? (string) $options['wsUrl'] : 'wss://ws-api.oneme.ru/websocket';
        $this->proxy = isset($options['proxy']) ? (string) $options['proxy'] : null;
        $this->useSsl = array_key_exists('useSsl', $options) ? (bool) $options['useSsl'] : true;
        $this->requestTimeout = isset($options['requestTimeout']) ? max(0.001, (float) $options['requestTimeout']) : 30.0;
        $this->pingInterval = array_key_exists('pingInterval', $options) ? max(0.0, (float) $options['pingInterval']) : 30.0;
        $this->connectTimeout = isset($options['connectTimeout']) ? max(0.001, (float) $options['connectTimeout']) : 30.0;
        $this->telemetry = array_key_exists('telemetry', $options) ? (bool) $options['telemetry'] : false;
        $this->reconnect = array_key_exists('reconnect', $options) ? (bool) $options['reconnect'] : true;
        $this->reconnectDelay = isset($options['reconnectDelay']) ? max(0.0, (float) $options['reconnectDelay']) : 1.0;
        $this->qrPollTimeout = isset($options['qrPollTimeout']) ? max(0.001, (float) $options['qrPollTimeout']) : 180.0;
        $this->executionSafetyMargin = isset($options['executionSafetyMargin']) ? max(0.0, (float) $options['executionSafetyMargin']) : 3.0;
        $this->deviceId = isset($options['deviceId']) ? (string) $options['deviceId'] : $this->uuidV4();
        $this->mtInstanceId = isset($options['mtInstanceId']) ? (string) $options['mtInstanceId'] : $this->uuidV4();
        $this->clientSessionId = isset($options['clientSessionId']) ? (int) $options['clientSessionId'] : random_int(1, 70);
        $this->userAgent = isset($options['userAgent']) && $options['userAgent'] instanceof MobileUserAgentPayload
            ? $options['userAgent']
            : MobileUserAgentPayload::defaultAndroid();
        $this->sync = isset($options['sync']) && $options['sync'] instanceof SyncOverrides
            ? $options['sync']
            : new SyncOverrides();
        $this->store = isset($options['store']) && $options['store'] instanceof SessionStoreInterface
            ? $options['store']
            : null;
        $this->authFlow = isset($options['authFlow']) && $options['authFlow'] instanceof AuthFlowInterface
            ? $options['authFlow']
            : null;
        $this->httpUploader = isset($options['httpUploader']) && $options['httpUploader'] instanceof HttpUploaderInterface
            ? $options['httpUploader']
            : null;
        $this->uploadChunkSize = isset($options['uploadChunkSize']) ? max(1, (int) $options['uploadChunkSize']) : 1024 * 1024;
        $this->uploadProcessingTimeout = isset($options['uploadProcessingTimeout']) ? max(0.001, (float) $options['uploadProcessingTimeout']) : 60.0;
        $this->uploadHttpTimeout = isset($options['uploadHttpTimeout']) ? max(0.001, (float) $options['uploadHttpTimeout']) : 900.0;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function assertNonEmptyString(string $value, string $name): void
    {
        if (trim($value) === '') {
            throw new PHPMaxException('Client option `' . $name . '` must not be empty');
        }
    }

    private function assertPort(int $port, string $name): void
    {
        if ($port <= 0 || $port > 65535) {
            throw new PHPMaxException('Client option `' . $name . '` must be between 1 and 65535');
        }
    }
}
