<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use InvalidArgumentException;

final class NavigationRules
{
    /** @var array<string, RouteProfile> */
    private $profiles;
    /** @var array<int, list<ScreenTransition>> */
    private $graph;

    /**
     * @param array<string, RouteProfile> $profiles
     * @param array<int, list<ScreenTransition>> $graph
     */
    public function __construct(array $profiles, array $graph)
    {
        if ($profiles === []) {
            throw new InvalidArgumentException('Navigation rules require at least one route profile');
        }

        foreach ($profiles as $name => $profile) {
            if (!$profile instanceof RouteProfile) {
                throw new InvalidArgumentException('Navigation profile `' . (string) $name . '` must be RouteProfile');
            }
        }

        foreach ($graph as $screen => $transitions) {
            if ($transitions === []) {
                throw new InvalidArgumentException('Navigation graph screen `' . (string) $screen . '` has no transitions');
            }
            foreach ($transitions as $transition) {
                if (!$transition instanceof ScreenTransition) {
                    throw new InvalidArgumentException('Navigation graph transitions must be ScreenTransition instances');
                }
            }
        }

        $this->profiles = $profiles;
        $this->graph = $graph;
    }

    public static function defaults(): self
    {
        return new self(
            [
                'quick' => new RouteProfile(2, 35.0, 95.0, 0.05, 180.0, 420.0, 0.30),
                'browse' => new RouteProfile(4, 70.0, 210.0, 0.12, 240.0, 720.0, 0.22),
                'read' => new RouteProfile(3, 140.0, 360.0, 0.25, 420.0, 1200.0, 0.18),
            ],
            [
                Screen::BACKGROUND => [
                    new ScreenTransition(Screen::CHATS, 10),
                    new ScreenTransition(Screen::SETTINGS, 1),
                ],
                Screen::CHATS => [
                    new ScreenTransition(Screen::CHAT, 7),
                    new ScreenTransition(Screen::CONTACTS, 2),
                    new ScreenTransition(Screen::SEARCH, 2),
                    new ScreenTransition(Screen::CALLS, 1),
                    new ScreenTransition(Screen::SETTINGS, 1),
                    new ScreenTransition(Screen::CHATS, 2),
                ],
                Screen::CHAT => [
                    new ScreenTransition(Screen::CHATS, 8),
                    new ScreenTransition(Screen::CHAT, 2),
                    new ScreenTransition(Screen::SETTINGS, 1),
                ],
                Screen::CONTACTS => [
                    new ScreenTransition(Screen::CHATS, 6),
                    new ScreenTransition(Screen::CHAT, 2),
                    new ScreenTransition(Screen::SEARCH, 1),
                ],
                Screen::SEARCH => [
                    new ScreenTransition(Screen::CHATS, 5),
                    new ScreenTransition(Screen::CHAT, 3),
                    new ScreenTransition(Screen::CONTACTS, 1),
                ],
                Screen::CALLS => [
                    new ScreenTransition(Screen::CHATS, 5),
                    new ScreenTransition(Screen::CONTACTS, 2),
                    new ScreenTransition(Screen::SETTINGS, 2),
                ],
                Screen::SETTINGS => [
                    new ScreenTransition(Screen::CHATS, 7),
                    new ScreenTransition(Screen::CONTACTS, 2),
                    new ScreenTransition(Screen::CALLS, 2),
                    new ScreenTransition(Screen::MINIAPP, 1),
                ],
                Screen::MINIAPP => [
                    new ScreenTransition(Screen::SETTINGS, 3),
                    new ScreenTransition(Screen::CHATS, 6),
                ],
            ]
        );
    }

    public function chooseProfile(): RouteProfile
    {
        $names = array_keys($this->profiles);
        $index = random_int(0, count($names) - 1);

        return $this->profiles[$names[$index]];
    }

    /**
     * @return list<ScreenTransition>
     */
    public function transitionsFor(int $screen): array
    {
        if (!isset($this->graph[$screen])) {
            throw new InvalidArgumentException('Navigation graph has no transitions for screen=' . $screen);
        }

        return $this->graph[$screen];
    }
}
