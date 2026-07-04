<?php

declare(strict_types=1);

namespace PHPMax\Auth;

use PHPMax\Api\Auth\AuthService;
use PHPMax\Config\ClientOptions;
use PHPMax\Domain\Auth\RequestQrResponse;
use PHPMax\Exception\PHPMaxException;

class QrAuthFlow implements AuthFlowInterface
{
    /** @var QrHandlerInterface */
    private $qrHandler;
    /** @var PasswordProviderInterface */
    private $passwordProvider;

    public function __construct(QrHandlerInterface $qrHandler, ?PasswordProviderInterface $passwordProvider = null)
    {
        $this->qrHandler = $qrHandler;
        $this->passwordProvider = $passwordProvider ?: new ConsolePasswordProvider();
    }

    public function authenticate(AuthService $authService, ClientOptions $options): AuthResult
    {
        $qr = $authService->requestQr();
        $this->qrHandler->showQr($qr->qrLink);

        if (!$this->pollQr($authService, $qr, $options)) {
            throw new PHPMaxException('QR authentication expired');
        }

        $result = $authService->confirmQr($qr->trackId);
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

        return new AuthResult(null);
    }

    private function pollQr(AuthService $authService, RequestQrResponse $qr, ClientOptions $options): bool
    {
        $expiresAt = $qr->expiresAt !== null ? $qr->expiresAt / 1000.0 : microtime(true);
        $deadline = min($expiresAt, microtime(true) + $options->qrPollTimeout);
        $interval = $qr->pollingInterval !== null && $qr->pollingInterval > 0
            ? $qr->pollingInterval / 1000.0
            : 1.0;

        while (microtime(true) < $deadline) {
            $status = $authService->checkQr($qr->trackId);
            if ($status->status !== null && $status->status->loginAvailable === true) {
                return true;
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0.0) {
                break;
            }
            usleep((int) floor(min($interval, $remaining) * 1000000));
        }

        return false;
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
