<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use InvalidArgumentException;

final class RouteProfile
{
    /** @var int */
    public $steps;
    /** @var float */
    public $minPause;
    /** @var float */
    public $maxPause;
    /** @var float */
    public $longPauseChance;
    /** @var float */
    public $minLongPause;
    /** @var float */
    public $maxLongPause;
    /** @var float */
    public $backChance;

    public function __construct(
        int $steps,
        float $minPause,
        float $maxPause,
        float $longPauseChance,
        float $minLongPause,
        float $maxLongPause,
        float $backChance
    ) {
        if ($steps < 0) {
            throw new InvalidArgumentException('Route profile steps must be non-negative');
        }

        $this->steps = $steps;
        $this->minPause = $minPause;
        $this->maxPause = $maxPause;
        $this->longPauseChance = max(0.0, min(1.0, $longPauseChance));
        $this->minLongPause = $minLongPause;
        $this->maxLongPause = $maxLongPause;
        $this->backChance = max(0.0, min(1.0, $backChance));
    }
}
