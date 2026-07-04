<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use PHPMax\Domain\Chat;
use PHPMax\Domain\ChatType;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use Throwable;

class TelemetryService
{
    /** @var App */
    private $app;
    /** @var TelemetryPayloadBuilder */
    private $builder;
    /** @var NavigationPlanner */
    private $planner;
    /** @var int */
    private $actionId = 0;
    /** @var int */
    private $lastNavTime;

    public function __construct(App $app, ?TelemetryPayloadBuilder $builder = null, ?NavigationPlanner $planner = null)
    {
        $this->app = $app;
        $this->builder = $builder ?: new TelemetryPayloadBuilder();
        $this->planner = $planner ?: new NavigationPlanner();
        $this->lastNavTime = TelemetryPayloadBuilder::nowMs();
    }

    /**
     * @param list<TelemetryEvent|array<string, mixed>> $events
     */
    public function sendEvents(array $events): bool
    {
        if ($events === []) {
            return true;
        }

        try {
            $this->app->invoke(Opcode::LOG, $this->builder->toPayload($events));

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function login(int $userId, ?int $sessionId = null): bool
    {
        return $this->sendEvents([
            $this->builder->login($userId, $sessionId !== null ? $sessionId : $this->app->options()->clientSessionId),
        ]);
    }

    /**
     * @param array<string, int> $extraParams
     */
    public function navigation(
        int $userId,
        int $screenFrom,
        int $screenTo,
        int $prevTime,
        int $actionId,
        array $extraParams = [],
        ?int $sessionId = null
    ): bool {
        return $this->sendEvents([
            $this->builder->navigation(
                $userId,
                $sessionId !== null ? $sessionId : $this->app->options()->clientSessionId,
                $screenFrom,
                $screenTo,
                $prevTime,
                $actionId,
                $extraParams
            ),
        ]);
    }

    public function openChat(int $userId, ?int $sessionId = null): bool
    {
        return $this->sendEvents([
            $this->builder->openChat($userId, $sessionId !== null ? $sessionId : $this->app->options()->clientSessionId),
        ]);
    }

    public function openChats(int $userId, ?int $sessionId = null): bool
    {
        return $this->sendEvents([
            $this->builder->openChats($userId, $sessionId !== null ? $sessionId : $this->app->options()->clientSessionId),
        ]);
    }

    /**
     * Builds one bounded navigation telemetry batch without sleeping or running
     * a background loop.
     *
     * @param list<Chat> $chats
     * @return list<TelemetryEvent>
     */
    public function plannedNavigationEvents(
        int $userId,
        ?int $sessionId = null,
        ?RouteProfile $profile = null,
        array $chats = []
    ): array {
        $resolvedSessionId = $sessionId !== null ? $sessionId : $this->app->options()->clientSessionId;
        $routeProfile = $profile ?: $this->planner->newProfile();
        $events = [];

        for ($step = 0; $step < $routeProfile->steps; $step++) {
            $screenFrom = $this->planner->currentScreen();
            $screenTo = $this->planner->nextScreen($routeProfile);
            $events[] = $this->navigationEvent($userId, $resolvedSessionId, $screenFrom, $screenTo, $chats);
            foreach ($this->renderEvents($userId, $resolvedSessionId, $screenTo) as $event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * @param list<Chat> $chats
     */
    public function sendPlannedNavigation(
        int $userId,
        ?int $sessionId = null,
        ?RouteProfile $profile = null,
        array $chats = []
    ): bool {
        $events = $this->plannedNavigationEvents($userId, $sessionId, $profile, $chats);
        $this->planner->resetToBackground();

        return $this->sendEvents($events);
    }

    public function resetNavigation(): void
    {
        $this->planner->resetToBackground();
    }

    /**
     * @param list<Chat> $chats
     */
    private function navigationEvent(int $userId, int $sessionId, int $screenFrom, int $screenTo, array $chats): TelemetryEvent
    {
        $event = $this->builder->navigation(
            $userId,
            $sessionId,
            $screenFrom,
            $screenTo,
            $this->lastNavTime,
            $this->nextActionId(),
            $this->sourceParams($screenTo, $userId, $chats)
        );
        $this->lastNavTime = (int) $event->time;

        return $event;
    }

    /**
     * @return list<TelemetryEvent>
     */
    private function renderEvents(int $userId, int $sessionId, int $screenTo): array
    {
        if ($screenTo === Screen::CHAT) {
            return [$this->builder->openChat($userId, $sessionId)];
        }

        if ($screenTo === Screen::CHATS && random_int(1, 100) <= 20) {
            return [$this->builder->openChats($userId, $sessionId)];
        }

        return [];
    }

    /**
     * @param list<Chat> $chats
     * @return array<string, int>
     */
    private function sourceParams(int $screenTo, int $userId, array $chats): array
    {
        if ($screenTo === Screen::CHATS) {
            return [
                'source_type' => 5,
                'source_id' => 1,
                'tab_config' => 2,
            ];
        }

        if ($screenTo !== Screen::CHAT) {
            return [];
        }

        $chat = $this->pickChat($chats);
        if ($chat === null || $chat->id === null) {
            return [
                'source_type' => 1,
                'source_id' => $userId,
            ];
        }

        return [
            'source_type' => $chat->type === ChatType::DIALOG ? 1 : 2,
            'source_id' => $chat->id,
        ];
    }

    /**
     * @param list<Chat> $chats
     */
    private function pickChat(array $chats): ?Chat
    {
        $available = [];
        foreach ($chats as $chat) {
            if ($chat instanceof Chat) {
                $available[] = $chat;
            }
        }

        if ($available === []) {
            return null;
        }

        return $available[random_int(0, count($available) - 1)];
    }

    private function nextActionId(): int
    {
        $this->actionId = ($this->actionId + 1) % 0xFFFFFFFF;

        return $this->actionId;
    }
}
