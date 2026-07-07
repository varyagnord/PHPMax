<?php

declare(strict_types=1);

use PHPMax\Api\Auth\AuthService;
use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Auth\EmailCodeProviderInterface;
use PHPMax\Auth\PasswordProviderInterface;
use PHPMax\Auth\QrAuthFlow;
use PHPMax\Auth\QrHandlerInterface;
use PHPMax\Auth\SmsAuthFlow;
use PHPMax\Auth\SmsCodeProviderInterface;
use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Domain\SyncState;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Session\SessionInfo;
use PHPMax\Session\SessionStoreInterface;
use PHPMax\Transport\TransportInterface;

final class AuthTestTransport implements TransportInterface
{
    /** @var list<string> */
    private $chunks;
    /** @var list<string> */
    public $sent = [];
    /** @var bool */
    private $connected = false;

    /**
     * @param list<string> $chunks
     */
    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function recv(int $length, float $timeout): string
    {
        $chunk = array_shift($this->chunks);
        if ($chunk === null) {
            throw new RuntimeException('No fake auth chunks left');
        }
        if (strlen($chunk) !== $length) {
            throw new RuntimeException('Expected chunk length ' . $length . ', got ' . strlen($chunk));
        }
        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class InMemoryAuthStore implements SessionStoreInterface
{
    /** @var list<SessionInfo> */
    public $saved = [];
    /** @var list<array{0: string, 1: string}> */
    public $updated = [];
    /** @var SessionInfo|null */
    public $session;

    public function __construct(?SessionInfo $session = null)
    {
        $this->session = $session;
    }

    public function saveSession(SessionInfo $sessionInfo): void
    {
        $this->session = $sessionInfo;
        $this->saved[] = $sessionInfo;
    }

    public function updateToken(string $oldToken, string $newToken): void
    {
        $this->updated[] = [$oldToken, $newToken];
        if ($this->session !== null && $this->session->token === $oldToken) {
            $this->session = new SessionInfo([
                'token' => $newToken,
                'deviceId' => $this->session->deviceId,
                'phone' => $this->session->phone,
                'mtInstanceId' => $this->session->mtInstanceId,
                'sync' => $this->session->sync,
            ]);
        }
    }

    public function loadSession(): ?SessionInfo
    {
        return $this->session;
    }

    public function loadSessionByDeviceId(string $deviceId): ?SessionInfo
    {
        return $this->session !== null && $this->session->deviceId === $deviceId ? $this->session : null;
    }

    public function loadSessionByPhone(string $phone): ?SessionInfo
    {
        return $this->session !== null && $this->session->phone === $phone ? $this->session : null;
    }

    public function deleteSession(string $token): void
    {
        if ($this->session !== null && $this->session->token === $token) {
            $this->session = null;
        }
    }

    public function deleteAllSessions(): void
    {
        $this->session = null;
    }

    public function close(): void
    {
    }
}

final class StaticAuthCodeProvider implements SmsCodeProviderInterface
{
    /** @var list<string> */
    public $phones = [];

    public function getCode(string $phone): string
    {
        $this->phones[] = $phone;
        return '111111';
    }
}

final class StaticAuthPasswordProvider implements PasswordProviderInterface
{
    /** @var list<string|null> */
    public $hints = [];

    public function getPassword(?string $hint = null): string
    {
        $this->hints[] = $hint;
        return 'secret';
    }
}

final class StaticAuthQrHandler implements QrHandlerInterface
{
    /** @var list<string> */
    public $urls = [];

    public function showQr(string $qrUrl): void
    {
        $this->urls[] = $qrUrl;
    }
}

final class StaticAuthEmailCodeProvider implements EmailCodeProviderInterface
{
    /** @var list<string> */
    public $emails = [];

    public function getCode(string $email): string
    {
        $this->emails[] = $email;
        return '222222';
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $protocol = new TcpProtocol();
    $frameChunks = static function (array $payload, int $opcode, int $seq) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, Command::RESPONSE));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (AuthTestTransport $transport, int $index = 0) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };

    $transport = new AuthTestTransport(array_merge(
        $frameChunks([
            'token' => 'sms-token',
            'codeLength' => 6,
            'requestMaxDuration' => 60,
            'requestCountLeft' => 2,
            'altActionDuration' => 5,
        ], Opcode::AUTH_REQUEST, 0),
        $frameChunks(['tokenAttrs' => ['LOGIN' => ['token' => 'login-token']]], Opcode::AUTH, 1)
    ));
    $manager = new ConnectionManager($transport, $protocol);
    $manager->open();
    $app = new App($manager, new ClientOptions(['store' => new InMemoryAuthStore(), 'requestTimeout' => 1.0]));
    $auth = $app->api()->auth;

    $start = $auth->requestCode('+79990000000');
    $result = $auth->sendCode($start->token, '111111');
    $assertSame('sms-token', $start->token);
    $assertSame('login-token', $result->loginToken());
    $assertSame(Opcode::AUTH_REQUEST, $decodeSent($transport, 0)->opcode);
    $assertSame('+79990000000', $decodeSent($transport, 0)->payload['phone']);
    $assertSame('START_AUTH', $decodeSent($transport, 0)->payload['type']);
    $assertSame('111111', $decodeSent($transport, 1)->payload['verifyCode']);
    $assertSame('CHECK_CODE', $decodeSent($transport, 1)->payload['authTokenType']);

    $emptyAuthTransport = new AuthTestTransport($frameChunks([], Opcode::AUTH_REQUEST, 0));
    $emptyAuthManager = new ConnectionManager($emptyAuthTransport, $protocol);
    $emptyAuthManager->open();
    $emptyAuthApp = new App($emptyAuthManager, new ClientOptions(['store' => new InMemoryAuthStore(), 'requestTimeout' => 1.0]));
    $assertThrows(PHPMaxException::class, static function () use ($emptyAuthApp): void {
        $emptyAuthApp->api()->auth->requestCode('+79990000000');
    }, 'Auth response models must reject empty payloads like PyMax require_payload_model');

    $loginStore = new InMemoryAuthStore(new SessionInfo([
        'token' => 'local-token',
        'deviceId' => 'device-test',
        'phone' => '+79990000000',
        'sync' => new SyncState(['chatsSync' => 1, 'contactsSync' => 2, 'draftsSync' => 3, 'presenceSync' => 4]),
    ]));
    $loginTransport = new AuthTestTransport($frameChunks([
        'profile' => ['contact' => ['id' => 42, 'names' => [['name' => 'Max', 'type' => 'NICK']]]],
        'chats' => [],
        'messages' => [],
        'contacts' => [],
        'token' => 'server-token',
        'time' => 777,
        'config' => ['hash' => 'cfg-hash'],
    ], Opcode::LOGIN, 0));
    $loginManager = new ConnectionManager($loginTransport, $protocol);
    $loginManager->open();
    $loginOptions = new ClientOptions([
        'store' => $loginStore,
        'mtInstanceId' => 'mt-test',
        'deviceId' => 'device-test',
        'requestTimeout' => 1.0,
    ]);
    $loginApp = new App($loginManager, $loginOptions);
    $loginApp->setSession($loginStore->loadSession());
    $login = $loginApp->api()->auth->mobileLogin();
    $sentLogin = $decodeSent($loginTransport, 0);
    $assertSame(Opcode::LOGIN, $sentLogin->opcode);
    $assertSame('local-token', $sentLogin->payload['token']);
    $assertSame(DeviceType::ANDROID, $sentLogin->payload['userAgent']['deviceType']);
    $assertSame('server-token', $login->token);
    $assertSame(777, $loginApp->session()->sync->chatsSync);
    $assertSame(777, $loginApp->session()->sync->contactsSync);
    $assertSame(777, $loginApp->session()->sync->draftsSync);
    $assertSame(777, $loginApp->session()->sync->presenceSync);
    $assertSame('cfg-hash', $loginApp->session()->sync->configHash);
    $assertSame('mt-test', $loginApp->session()->mtInstanceId);
    $savedLoginSession = $loginStore->saved[count($loginStore->saved) - 1];
    $assertSame($loginApp->session(), $savedLoginSession);
    $assertSame('local-token', $savedLoginSession->token);
    $assertSame('device-test', $savedLoginSession->deviceId);
    $assertSame('+79990000000', $savedLoginSession->phone);
    $assertSame('mt-test', $savedLoginSession->mtInstanceId);
    $assertSame(777, $savedLoginSession->sync->contactsSync);
    $assertSame(777, $savedLoginSession->sync->draftsSync);
    $assertSame(777, $savedLoginSession->sync->presenceSync);

    $emptyLoginStore = new InMemoryAuthStore(new SessionInfo([
        'token' => 'local-token-empty',
        'deviceId' => 'device-empty',
        'phone' => '+79990000000',
    ]));
    $emptyLoginTransport = new AuthTestTransport($frameChunks([], Opcode::LOGIN, 0));
    $emptyLoginManager = new ConnectionManager($emptyLoginTransport, $protocol);
    $emptyLoginManager->open();
    $emptyLoginApp = new App($emptyLoginManager, new ClientOptions([
        'store' => $emptyLoginStore,
        'deviceId' => 'device-empty',
        'requestTimeout' => 1.0,
    ]));
    $emptyLoginApp->setSession($emptyLoginStore->loadSession());
    $assertThrows(PHPMaxException::class, static function () use ($emptyLoginApp): void {
        $emptyLoginApp->api()->auth->mobileLogin();
    }, 'Empty LOGIN payload must fail before LoginResponse hydration');
    $assertSame([], $emptyLoginStore->saved, 'Failed LOGIN response must not update session store');

    $webUa = new MobileUserAgentPayload([
        'deviceType' => DeviceType::WEB,
        'appVersion' => '26.5.5',
        'osVersion' => 'Linux',
        'timezone' => 'Europe/Moscow',
        'screen' => '1080x1920 1.0x',
        'locale' => 'ru',
        'deviceName' => 'Chrome',
        'deviceLocale' => 'ru',
    ]);
    $webStore = new InMemoryAuthStore(new SessionInfo(['token' => 'web-token', 'deviceId' => 'web-device', 'phone' => '']));
    $webTransport = new AuthTestTransport($frameChunks([
        'profile' => ['contact' => ['id' => 44, 'names' => []]],
        'token' => 'web-server-token',
    ], Opcode::LOGIN, 0));
    $webManager = new ConnectionManager($webTransport, $protocol);
    $webManager->open();
    $webApp = new App($webManager, new ClientOptions(['store' => $webStore, 'userAgent' => $webUa, 'requestTimeout' => 1.0]));
    $webApp->setSession($webStore->loadSession());
    $webApp->api()->auth->login($webUa);
    $webPayload = $decodeSent($webTransport, 0)->payload;
    $assert(!isset($webPayload['userAgent']), 'Web login must not send mobile userAgent payload');
    $assertSame(40, $webPayload['chatsCount']);

    $flowTransport = new AuthTestTransport(array_merge(
        $frameChunks([
            'token' => 'sms-token',
            'codeLength' => 6,
            'requestMaxDuration' => 60,
            'requestCountLeft' => 1,
            'altActionDuration' => 0,
        ], Opcode::AUTH_REQUEST, 0),
        $frameChunks(['passwordChallenge' => ['trackId' => 'track-1', 'hint' => 'hint']], Opcode::AUTH, 1),
        $frameChunks(['tokenAttrs' => ['LOGIN' => ['token' => '2fa-token']]], Opcode::AUTH_LOGIN_CHECK_PASSWORD, 2)
    ));
    $flowManager = new ConnectionManager($flowTransport, $protocol);
    $flowManager->open();
    $flowApp = new App($flowManager, new ClientOptions(['store' => new InMemoryAuthStore(), 'requestTimeout' => 1.0]));
    $codeProvider = new StaticAuthCodeProvider();
    $passwordProvider = new StaticAuthPasswordProvider();
    $flow = new SmsAuthFlow($codeProvider, $passwordProvider);
    $authResult = $flow->authenticate($flowApp->api()->auth, new ClientOptions(['phone' => '+79990000000']));
    $assertSame('2fa-token', $authResult->token);
    $assertSame(['+79990000000'], $codeProvider->phones);
    $assertSame(['hint'], $passwordProvider->hints);

    $passwordResponse = \PHPMax\Domain\Auth\CheckPasswordResponse::fromArray([
        'tokenAttrs' => ['LOGIN' => ['token' => '2fa-token-with-error-false']],
        'error' => false,
    ]);
    $assertSame('2fa-token-with-error-false', $passwordResponse->loginToken());
    $assertSame(false, $passwordResponse->error);
    $userWithFalseStrings = \PHPMax\Domain\User::fromArray([
        'id' => 42,
        'country' => false,
        'baseRawUrl' => false,
        'baseUrl' => false,
        'status' => false,
        'description' => false,
        'link' => false,
    ]);
    $assertSame('', $userWithFalseStrings->country);
    $assertSame('', $userWithFalseStrings->baseUrl);
    $assertSame('', $userWithFalseStrings->status);

    $nowMs = (int) floor(microtime(true) * 1000);
    $qrTransport = new AuthTestTransport(array_merge(
        $frameChunks([
            'expiresAt' => $nowMs + 60000,
            'pollingInterval' => 250,
            'qrLink' => 'max://qr-login/track-1',
            'trackId' => 'track-1',
            'ttl' => 60000,
        ], Opcode::GET_QR, 0),
        $frameChunks([
            'status' => [
                'expiresAt' => $nowMs + 60000,
                'loginAvailable' => true,
            ],
        ], Opcode::GET_QR_STATUS, 1),
        $frameChunks(['tokenAttrs' => ['LOGIN' => ['token' => 'qr-login-token']]], Opcode::LOGIN_BY_QR, 2),
        $frameChunks([], Opcode::AUTH_QR_APPROVE, 3)
    ));
    $qrManager = new ConnectionManager($qrTransport, $protocol);
    $qrManager->open();
    $qrApp = new App($qrManager, new ClientOptions(['requestTimeout' => 1.0]));

    $qrInfo = $qrApp->api()->auth->requestQr();
    $qrStatus = $qrApp->api()->auth->checkQr($qrInfo->trackId);
    $qrResult = $qrApp->api()->auth->confirmQr($qrInfo->trackId);
    $qrApproved = $qrApp->api()->auth->authorizeQrLogin($qrInfo->qrLink);
    $assertSame('max://qr-login/track-1', $qrInfo->qrLink);
    $assertSame('track-1', $qrInfo->trackId);
    $assertSame(true, $qrStatus->status->loginAvailable);
    $assertSame('qr-login-token', $qrResult->loginToken());
    $assertSame(true, $qrApproved);
    $assertSame(Opcode::GET_QR, $decodeSent($qrTransport, 0)->opcode);
    $assertSame([], $decodeSent($qrTransport, 0)->payload);
    $assertSame(Opcode::GET_QR_STATUS, $decodeSent($qrTransport, 1)->opcode);
    $assertSame(['trackId' => 'track-1'], $decodeSent($qrTransport, 1)->payload);
    $assertSame(Opcode::LOGIN_BY_QR, $decodeSent($qrTransport, 2)->opcode);
    $assertSame(['trackId' => 'track-1'], $decodeSent($qrTransport, 2)->payload);
    $assertSame(Opcode::AUTH_QR_APPROVE, $decodeSent($qrTransport, 3)->opcode);
    $assertSame(['qrLink' => 'max://qr-login/track-1'], $decodeSent($qrTransport, 3)->payload);

    $qrFlowTransport = new AuthTestTransport(array_merge(
        $frameChunks([
            'expiresAt' => $nowMs + 60000,
            'pollingInterval' => 1,
            'qrLink' => 'max://qr-login/track-2',
            'trackId' => 'track-2',
            'ttl' => 60000,
        ], Opcode::GET_QR, 0),
        $frameChunks([
            'status' => [
                'expiresAt' => $nowMs + 60000,
                'loginAvailable' => true,
            ],
        ], Opcode::GET_QR_STATUS, 1),
        $frameChunks(['tokenAttrs' => ['LOGIN' => ['token' => 'qr-flow-token']]], Opcode::LOGIN_BY_QR, 2)
    ));
    $qrFlowManager = new ConnectionManager($qrFlowTransport, $protocol);
    $qrFlowManager->open();
    $qrFlowApp = new App($qrFlowManager, new ClientOptions(['requestTimeout' => 1.0, 'qrPollTimeout' => 1.0]));
    $qrHandler = new StaticAuthQrHandler();
    $qrFlow = new QrAuthFlow($qrHandler, new StaticAuthPasswordProvider());
    $qrFlowResult = $qrFlow->authenticate($qrFlowApp->api()->auth, $qrFlowApp->options());
    $assertSame('qr-flow-token', $qrFlowResult->token);
    $assertSame(['max://qr-login/track-2'], $qrHandler->urls);
    $assertSame(3, count($qrFlowTransport->sent));
    $assertSame(Opcode::GET_QR, $decodeSent($qrFlowTransport, 0)->opcode);
    $assertSame(Opcode::GET_QR_STATUS, $decodeSent($qrFlowTransport, 1)->opcode);
    $assertSame(Opcode::LOGIN_BY_QR, $decodeSent($qrFlowTransport, 2)->opcode);

    $twoFactorTransport = new AuthTestTransport(array_merge(
        $frameChunks(['trackId' => '2fa-track'], Opcode::AUTH_CREATE_TRACK, 0),
        $frameChunks([], Opcode::AUTH_VALIDATE_PASSWORD, 1),
        $frameChunks([], Opcode::AUTH_VERIFY_EMAIL, 2),
        $frameChunks([], Opcode::AUTH_CHECK_EMAIL, 3),
        $frameChunks([], Opcode::AUTH_VALIDATE_HINT, 4),
        $frameChunks([], Opcode::AUTH_SET_2FA, 5)
    ));
    $twoFactorManager = new ConnectionManager($twoFactorTransport, $protocol);
    $twoFactorManager->open();
    $twoFactorApp = new App($twoFactorManager, new ClientOptions(['requestTimeout' => 1.0]));
    $emailProvider = new StaticAuthEmailCodeProvider();
    $assert($twoFactorApp->api()->auth->setTwoFactor('new-password', 'me@example.test', 'my hint', $emailProvider));
    $assertSame(['me@example.test'], $emailProvider->emails);
    $assertSame(Opcode::AUTH_CREATE_TRACK, $decodeSent($twoFactorTransport, 0)->opcode);
    $assertSame(['type' => 0], $decodeSent($twoFactorTransport, 0)->payload);
    $assertSame(Opcode::AUTH_VALIDATE_PASSWORD, $decodeSent($twoFactorTransport, 1)->opcode);
    $assertSame(['trackId' => '2fa-track', 'password' => 'new-password'], $decodeSent($twoFactorTransport, 1)->payload);
    $assertSame(Opcode::AUTH_VERIFY_EMAIL, $decodeSent($twoFactorTransport, 2)->opcode);
    $assertSame(['trackId' => '2fa-track', 'email' => 'me@example.test'], $decodeSent($twoFactorTransport, 2)->payload);
    $assertSame(Opcode::AUTH_CHECK_EMAIL, $decodeSent($twoFactorTransport, 3)->opcode);
    $assertSame(['trackId' => '2fa-track', 'verifyCode' => '222222'], $decodeSent($twoFactorTransport, 3)->payload);
    $assertSame(Opcode::AUTH_VALIDATE_HINT, $decodeSent($twoFactorTransport, 4)->opcode);
    $assertSame(['trackId' => '2fa-track', 'hint' => 'my hint'], $decodeSent($twoFactorTransport, 4)->payload);
    $setPayload = $decodeSent($twoFactorTransport, 5)->payload;
    $assertSame(Opcode::AUTH_SET_2FA, $decodeSent($twoFactorTransport, 5)->opcode);
    $assertSame([0, 3, 4], $setPayload['expectedCapabilities']);
    $assertSame('2fa-track', $setPayload['trackId']);
    $assertSame('new-password', $setPayload['password']);
    $assertSame('my hint', $setPayload['hint']);

    $removeTransport = new AuthTestTransport(array_merge(
        $frameChunks(['trackId' => 'remove-track'], Opcode::AUTH_CREATE_TRACK, 0),
        $frameChunks([], Opcode::AUTH_CHECK_PASSWORD, 1),
        $frameChunks([], Opcode::AUTH_SET_2FA, 2)
    ));
    $removeManager = new ConnectionManager($removeTransport, $protocol);
    $removeManager->open();
    $removeApp = new App($removeManager, new ClientOptions(['requestTimeout' => 1.0]));
    $assert($removeApp->api()->auth->removeTwoFactor('old-password'));
    $assertSame(Opcode::AUTH_CHECK_PASSWORD, $decodeSent($removeTransport, 1)->opcode);
    $assertSame(['trackId' => 'remove-track', 'password' => 'old-password'], $decodeSent($removeTransport, 1)->payload);
    $removePayload = $decodeSent($removeTransport, 2)->payload;
    $assertSame(['trackId' => 'remove-track', 'remove2fa' => true, 'expectedCapabilities' => [5]], $removePayload);

    $changeTransport = new AuthTestTransport(array_merge(
        $frameChunks(['trackId' => 'change-track'], Opcode::AUTH_CREATE_TRACK, 0),
        $frameChunks([], Opcode::AUTH_CHECK_PASSWORD, 1),
        $frameChunks([], Opcode::AUTH_VALIDATE_PASSWORD, 2),
        $frameChunks([], Opcode::AUTH_SET_2FA, 3)
    ));
    $changeManager = new ConnectionManager($changeTransport, $protocol);
    $changeManager->open();
    $changeApp = new App($changeManager, new ClientOptions(['requestTimeout' => 1.0]));
    $assert($changeApp->api()->auth->changePassword('old-password', 'new-password'));
    $assertSame(['trackId' => 'change-track', 'password' => 'old-password'], $decodeSent($changeTransport, 1)->payload);
    $assertSame(['trackId' => 'change-track', 'password' => 'new-password'], $decodeSent($changeTransport, 2)->payload);
    $assertSame([
        'expectedCapabilities' => [1],
        'trackId' => 'change-track',
        'password' => 'new-password',
    ], $decodeSent($changeTransport, 3)->payload);

    $assert($changeApp->api()->auth->profileHasTwoFactor([2]), 'Profile option 2 must mean 2FA enabled');
    $assert(!$changeApp->api()->auth->profileHasTwoFactor([1, 3, 4]), 'Profile without option 2 must mean 2FA disabled');
    $assert(!$changeApp->api()->auth->checkTwoFactor(), 'Missing App profile must mean 2FA disabled');
    $changeApp->setProfile(PHPMax\Domain\Profile::fromArray([
        'contact' => ['id' => 501, 'names' => []],
        'profileOptions' => [2],
    ]));
    $assert($changeApp->api()->auth->checkTwoFactor(), 'AuthService::checkTwoFactor must read App profile options like PyMax check_2fa');

    $handshakeRaw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, Opcode::SESSION_INIT, 0, [], Command::RESPONSE));
    $profileRaw = $protocol->encode(new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::LOGIN,
        1,
        [
            'profile' => ['contact' => ['id' => 500, 'names' => []], 'profileOptions' => [2]],
            'chats' => [],
            'messages' => [],
            'contacts' => [],
            'token' => 'client-2fa-token',
            'time' => 1000,
            'config' => ['hash' => 'cfg'],
        ],
        Command::RESPONSE
    ));
    $clientTransport = new AuthTestTransport([
        substr($handshakeRaw, 0, 10),
        substr($handshakeRaw, 10),
        substr($profileRaw, 0, 10),
        substr($profileRaw, 10),
    ]);
    $client = new Client(new ClientOptions([
        'token' => 'client-token',
        'store' => new InMemoryAuthStore(),
        'requestTimeout' => 1.0,
    ]), new ConnectionManager($clientTransport, $protocol));
    $client->open();
    $assert($client->checkTwoFactor(), 'Client shortcut must read current login profile options');
};
