<?php

declare(strict_types=1);

namespace PHPMax\Api\Auth;

use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Auth\ConsoleEmailCodeProvider;
use PHPMax\Auth\EmailCodeProviderInterface;
use PHPMax\Domain\Auth\CheckCodeResponse;
use PHPMax\Domain\Auth\CheckPasswordResponse;
use PHPMax\Domain\Auth\CheckQrResponse;
use PHPMax\Domain\Auth\ConfirmRegistrationResponse;
use PHPMax\Domain\Auth\RequestQrResponse;
use PHPMax\Domain\Auth\StartAuthResponse;
use PHPMax\Domain\LoginResponse;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use PHPMax\Session\SessionInfo;

class AuthService
{
    /** @var App */
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function requestCode(string $phone): StartAuthResponse
    {
        $payload = new RequestCodePayload(['phone' => $phone]);
        $response = $this->app->invoke(Opcode::AUTH_REQUEST, $payload->toArray());

        return StartAuthResponse::fromArray($this->requirePayload($response->payload));
    }

    public function sendCode(string $token, string $verifyCode): CheckCodeResponse
    {
        $payload = new SendCodePayload([
            'token' => $token,
            'verifyCode' => $verifyCode,
        ]);
        $response = $this->app->invoke(Opcode::AUTH, $payload->toArray());

        return CheckCodeResponse::fromArray($this->requirePayload($response->payload));
    }

    public function checkPassword(string $trackId, string $password): CheckPasswordResponse
    {
        $payload = new CheckPasswordChallengePayload([
            'trackId' => $trackId,
            'password' => $password,
        ]);
        $response = $this->app->invoke(Opcode::AUTH_LOGIN_CHECK_PASSWORD, $payload->toArray());

        return CheckPasswordResponse::fromArray($this->requirePayload($response->payload));
    }

    public function login(MobileUserAgentPayload $userAgent): LoginResponse
    {
        if ($userAgent->deviceType === DeviceType::WEB) {
            return $this->webLogin();
        }

        return $this->mobileLogin();
    }

    public function mobileLogin(): LoginResponse
    {
        $session = $this->app->session();
        if ($session === null) {
            throw new PHPMaxException('No session available for login');
        }

        $sync = $this->app->options()->sync->resolve($session->sync);
        $payload = SyncPayload::fromSyncState($this->app->options()->userAgent, $session->token, $sync);
        $response = $this->app->invoke(Opcode::LOGIN, $payload->toArray());
        $login = LoginResponse::fromArray($this->requirePayload($response->payload));
        $this->updateSession($login);

        return $login;
    }

    public function webLogin(): LoginResponse
    {
        $session = $this->app->session();
        if ($session === null) {
            throw new PHPMaxException('No session available for login');
        }

        $sync = $this->app->options()->sync->resolve($session->sync);
        $payload = WebSyncPayload::fromSyncState($session->token, $sync);
        $response = $this->app->invoke(Opcode::LOGIN, $payload->toArray());
        $login = LoginResponse::fromArray($this->requirePayload($response->payload));
        $this->updateSession($login);

        return $login;
    }

    public function confirmRegistration(string $firstName, ?string $lastName, string $token): ConfirmRegistrationResponse
    {
        $payload = new ConfirmRegistrationPayload([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'token' => $token,
        ]);
        $response = $this->app->invoke(Opcode::AUTH_CONFIRM, $payload->toArray());

        return ConfirmRegistrationResponse::fromArray($this->requirePayload($response->payload));
    }

    public function requestQr(): RequestQrResponse
    {
        $response = $this->app->invoke(Opcode::GET_QR, []);

        return RequestQrResponse::fromArray($this->requirePayload($response->payload));
    }

    public function checkQr(string $trackId): CheckQrResponse
    {
        $payload = new CheckQrPayload(['trackId' => $trackId]);
        $response = $this->app->invoke(Opcode::GET_QR_STATUS, $payload->toArray());

        return CheckQrResponse::fromArray($this->requirePayload($response->payload));
    }

    public function confirmQr(string $trackId): CheckCodeResponse
    {
        $payload = new ConfirmQrPayload(['trackId' => $trackId]);
        $response = $this->app->invoke(Opcode::LOGIN_BY_QR, $payload->toArray());

        return CheckCodeResponse::fromArray($this->requirePayload($response->payload));
    }

    public function authorizeQrLogin(string $qrLink): bool
    {
        $payload = new ApproveQrLoginPayload(['qrLink' => $qrLink]);
        $this->app->invoke(Opcode::AUTH_QR_APPROVE, $payload->toArray());

        return true;
    }

    public function createAuthTrack(): string
    {
        $payload = new CreateAuthTrackPayload();
        $response = $this->app->invoke(Opcode::AUTH_CREATE_TRACK, $payload->toArray());
        $trackId = $response->payload['trackId'] ?? null;
        if (!is_string($trackId) || $trackId === '') {
            throw new PHPMaxException('Failed to create auth track');
        }

        return $trackId;
    }

    public function setTwoFactor(
        string $password,
        ?string $email = null,
        ?string $hint = null,
        ?EmailCodeProviderInterface $emailCodeProvider = null
    ): bool {
        $trackId = $this->createAuthTrack();
        $this->setPassword($trackId, $password);

        $hasEmail = false;
        $hasHint = false;
        if ($email !== null) {
            $this->setEmail($trackId, $email, $emailCodeProvider ?: new ConsoleEmailCodeProvider());
            $hasEmail = true;
        }
        if ($hint !== null) {
            $this->setHint($trackId, $hint);
            $hasHint = true;
        }

        $expectedCapabilities = [TwoFactorAction::SET_PASSWORD];
        if ($hasHint) {
            $expectedCapabilities[] = TwoFactorAction::HINT;
        }
        if ($hasEmail) {
            $expectedCapabilities[] = TwoFactorAction::EMAIL;
        }

        $payload = new SetTwoFactorPayload([
            'expectedCapabilities' => $expectedCapabilities,
            'trackId' => $trackId,
            'password' => $password,
            'hint' => $hint,
        ]);
        $this->app->invoke(Opcode::AUTH_SET_2FA, $payload->toArray());

        return true;
    }

    public function removeTwoFactor(string $password): bool
    {
        $trackId = $this->createAuthTrack();
        $this->checkCurrentTwoFactorPassword($trackId, $password);
        $payload = new RemoveTwoFactorPayload(['trackId' => $trackId]);
        $this->app->invoke(Opcode::AUTH_SET_2FA, $payload->toArray());

        return true;
    }

    public function changePassword(string $passwordOld, string $passwordNew): bool
    {
        $trackId = $this->createAuthTrack();
        $this->checkCurrentTwoFactorPassword($trackId, $passwordOld);
        $this->setPassword($trackId, $passwordNew);
        $payload = new SetTwoFactorPayload([
            'expectedCapabilities' => [TwoFactorAction::UPDATE_PASSWORD],
            'trackId' => $trackId,
            'password' => $passwordNew,
        ]);
        $this->app->invoke(Opcode::AUTH_SET_2FA, $payload->toArray());

        return true;
    }

    public function profileHasTwoFactor(?array $profileOptions): bool
    {
        if ($profileOptions === null || $profileOptions === []) {
            return false;
        }

        foreach ($profileOptions as $option) {
            if ((int) $option === ProfileOptions::SECOND_FACTOR_PASSWORD_ENABLED) {
                return true;
            }
        }

        return false;
    }

    public function checkTwoFactor(): bool
    {
        $profile = $this->app->me();

        return $this->profileHasTwoFactor($profile !== null ? $profile->profileOptions : null);
    }

    private function setPassword(string $trackId, string $password): bool
    {
        $payload = new SetPasswordPayload([
            'trackId' => $trackId,
            'password' => $password,
        ]);
        $this->app->invoke(Opcode::AUTH_VALIDATE_PASSWORD, $payload->toArray());

        return true;
    }

    private function checkCurrentTwoFactorPassword(string $trackId, string $password): bool
    {
        $payload = new SetPasswordPayload([
            'trackId' => $trackId,
            'password' => $password,
        ]);
        $this->app->invoke(Opcode::AUTH_CHECK_PASSWORD, $payload->toArray());

        return true;
    }

    private function setHint(string $trackId, string $hint): bool
    {
        $payload = new SetHintPayload([
            'trackId' => $trackId,
            'hint' => $hint,
        ]);
        $this->app->invoke(Opcode::AUTH_VALIDATE_HINT, $payload->toArray());

        return true;
    }

    private function setEmail(string $trackId, string $email, EmailCodeProviderInterface $provider): bool
    {
        $payload = new RequestEmailCodePayload([
            'trackId' => $trackId,
            'email' => $email,
        ]);
        $this->app->invoke(Opcode::AUTH_VERIFY_EMAIL, $payload->toArray());

        $code = $provider->getCode($email);
        $confirmPayload = new SendEmailCodePayload([
            'trackId' => $trackId,
            'verifyCode' => $code,
        ]);
        $this->app->invoke(Opcode::AUTH_CHECK_EMAIL, $confirmPayload->toArray());

        return true;
    }

    private function updateSession(LoginResponse $response): void
    {
        $session = $this->app->session();
        if ($session === null) {
            return;
        }

        $updated = new SessionInfo([
            'token' => $session->token,
            'deviceId' => $session->deviceId,
            'phone' => $session->phone,
            'mtInstanceId' => $this->app->options()->mtInstanceId,
            'sync' => $response->updateSyncState($session->sync),
        ]);
        $this->app->setSession($updated);
        $this->app->store()->saveSession($updated);
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<string, mixed>
     */
    private function requirePayload(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            throw new PHPMaxException('Missing response payload');
        }

        return $payload;
    }
}
