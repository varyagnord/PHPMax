<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

class TelemetryPayloadBuilder
{
    public function login(int $userId, int $sessionId): TelemetryEvent
    {
        return new TelemetryEvent([
            'time' => self::nowMs(),
            'userId' => $userId,
            'type' => 'PERF',
            'event' => 'login',
            'params' => [
                'properties' => [
                    'connection_type' => 2,
                    'vpn' => 0,
                    'class' => 2,
                    'background' => 1,
                    'warm_start' => 1,
                ],
                'errorType' => 100,
            ],
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * @param array<string, int> $extraParams
     */
    public function navigation(
        int $userId,
        int $sessionId,
        int $screenFrom,
        int $screenTo,
        int $prevTime,
        int $actionId,
        array $extraParams = []
    ): TelemetryEvent {
        return new TelemetryEvent([
            'time' => self::nowMs(),
            'userId' => $userId,
            'type' => 'NAV',
            'event' => 'GO',
            'params' => array_merge([
                'prev_time' => $prevTime,
                'screen_to' => $screenTo,
                'action_id' => $actionId,
                'screen_from' => $screenFrom,
            ], $extraParams),
            'sessionId' => $sessionId,
        ]);
    }

    public function openChat(int $userId, int $sessionId): TelemetryEvent
    {
        $messages = random_int(60, 240);
        $render = random_int(50, 260);

        return new TelemetryEvent([
            'time' => self::nowMs(),
            'userId' => $userId,
            'type' => 'PERF',
            'event' => 'open_chat_to_render',
            'params' => [
                'spans' => [
                    ['duration' => $messages + $render, 'name' => 'open_chat_to_render'],
                    ['duration' => $messages, 'name' => 'messages_list_created'],
                    ['duration' => $render, 'name' => 'messages_render'],
                ],
                'properties' => [
                    'class' => 2,
                    'warm' => 1,
                    'flow' => 1,
                ],
            ],
            'sessionId' => $sessionId,
        ]);
    }

    public function openChats(int $userId, int $sessionId): TelemetryEvent
    {
        $created = random_int(50, 230);
        $rendered = random_int(180, 650);

        return new TelemetryEvent([
            'time' => self::nowMs(),
            'userId' => $userId,
            'type' => 'PERF',
            'event' => 'open_chats_to_render',
            'params' => [
                'spans' => [
                    ['duration' => $created + $rendered, 'name' => 'open_chats_to_render'],
                    ['duration' => $created, 'name' => 'chats_tab_created'],
                    ['duration' => $rendered, 'name' => 'chat_list_render'],
                ],
                'properties' => ['class' => 2],
            ],
            'sessionId' => $sessionId,
        ]);
    }

    /**
     * @param list<TelemetryEvent|array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function toPayload(array $events): array
    {
        return (new TelemetryPayload(['events' => $events]))->toArray();
    }

    public static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
