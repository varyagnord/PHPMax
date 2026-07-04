<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

final class NavigationPlanner
{
    /** @var NavigationRules */
    private $rules;
    /** @var int */
    private $currentScreen;
    /** @var list<int> */
    private $history;

    public function __construct(?NavigationRules $rules = null)
    {
        $this->rules = $rules ?: NavigationRules::defaults();
        $this->currentScreen = Screen::BACKGROUND;
        $this->history = [];
    }

    public function newProfile(): RouteProfile
    {
        return $this->rules->chooseProfile();
    }

    public function nextScreen(RouteProfile $profile): int
    {
        if ($this->history !== [] && $this->chance($profile->backChance)) {
            $this->currentScreen = array_pop($this->history);

            return $this->currentScreen;
        }

        $nextScreen = $this->weightedChoice($this->rules->transitionsFor($this->currentScreen));
        if ($nextScreen !== $this->currentScreen) {
            $this->history[] = $this->currentScreen;
            if (count($this->history) > 4) {
                array_shift($this->history);
            }
        }

        $this->currentScreen = $nextScreen;

        return $nextScreen;
    }

    public function resetToBackground(): void
    {
        $this->currentScreen = Screen::BACKGROUND;
        $this->history = [];
    }

    public function currentScreen(): int
    {
        return $this->currentScreen;
    }

    /**
     * @return list<int>
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * @param list<ScreenTransition> $transitions
     */
    private function weightedChoice(array $transitions): int
    {
        $total = 0;
        foreach ($transitions as $transition) {
            $total += $transition->weight;
        }

        $point = random_int(1, $total);
        $current = 0;
        foreach ($transitions as $transition) {
            $current += $transition->weight;
            if ($point <= $current) {
                return $transition->screen;
            }
        }

        return $transitions[count($transitions) - 1]->screen;
    }

    private function chance(float $chance): bool
    {
        if ($chance <= 0.0) {
            return false;
        }
        if ($chance >= 1.0) {
            return true;
        }

        return random_int(1, 1000000) <= (int) floor($chance * 1000000);
    }
}
