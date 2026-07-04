<?php

declare(strict_types=1);

namespace PHPMax\Api\Bots;

use PHPMax\Domain\InitData;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;

class BotsService
{
    /** @var App */
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getInitData(int $botId, ?int $chatId = null, ?string $startParam = null): InitData
    {
        $payload = new RequestInitDataPayload([
            'botId' => $botId,
            'chatId' => $chatId,
            'startParam' => $startParam,
        ]);
        $response = $this->app->invoke(Opcode::WEB_APP_INIT_DATA, $payload->toArray());

        return InitData::fromArray($this->requireResponsePayload($response->payload));
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireResponsePayload(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            throw new PHPMaxException('Missing payload in response');
        }

        return $payload;
    }
}
