<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Api\Auth\AuthService;
use PHPMax\Config\ClientOptions;
use PHPMax\Exception\PHPMaxException;

class SmsAuthFlow implements AuthFlowInterface
{
    /** @var SmsCodeProviderInterface */
    private $codeProvider;
    /** @var PasswordProviderInterface */
    private $passwordProvider;

    public function __construct(SmsCodeProviderInterface $codeProvider, ?PasswordProviderInterface $passwordProvider = null)
    {
        $this->codeProvider = $codeProvider;
        $this->passwordProvider = $passwordProvider ?: new ConsolePasswordProvider();
    }

    public function authenticate(AuthService $authService, ClientOptions $options): AuthResult
    {
        if ($options->phone === null || $options->phone === '') {
            throw new PHPMaxException('Phone is required for SMS authentication');
        }

        $start = $authService->requestCode($options->phone);
        $code = $this->codeProvider->getCode($options->phone);
        $result = $authService->sendCode($start->token, $code);

        if ($result->loginToken() !== null) {
            return new AuthResult($result->loginToken());
        }

        if ($result->passwordChallenge !== null) {
            return new AuthResult($this->authenticateWithPassword(
                $authService,
                $result->passwordChallenge->trackId,
                $result->passwordChallenge->hint
            ));
        }

        if ($result->registerToken() !== null) {
            if ($options->registrationFirstName === null || $options->registrationFirstName === '') {
                throw new PHPMaxException('Registration first name is required to register a new account');
            }
            $registered = $authService->confirmRegistration(
                $options->registrationFirstName,
                $options->registrationLastName,
                $result->registerToken()
            );

            return new AuthResult($registered->token);
        }

        return new AuthResult(null);
    }

    private function authenticateWithPassword(AuthService $authService, string $trackId, ?string $hint): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $password = $this->passwordProvider->getPassword($hint);
            if ($password === '') {
                continue;
            }
            $response = $authService->checkPassword($trackId, $password);
            if ($response->error !== null) {
                continue;
            }
            if ($response->loginToken() !== null) {
                return $response->loginToken();
            }
        }

        throw new PHPMaxException('2FA password authentication failed');
    }
}

