<?php

declare(strict_types=1);

namespace PHPMax\Api\Telemetry;

use InvalidArgumentException;

final class ScreenTransition
{
    /** @var int */
    public $screen;
    /** @var int */
    public $weight;

    public function __construct(int $screen, int $weight)
    {
        if ($weight < 1) {
            throw new InvalidArgumentException('Screen transition weight must be positive');
        }

        $this->screen = $screen;
        $this->weight = $weight;
    }
}
