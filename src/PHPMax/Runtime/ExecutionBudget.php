<?php

declare(strict_types=1);

namespace PHPMax\Runtime;

class ExecutionBudget
{
    /** @var float */
    private $deadline;

    public function __construct(float $seconds)
    {
        $this->deadline = microtime(true) + max(0.0, $seconds);
    }

    public static function fromRequestedSeconds(int $seconds, float $safetyMargin): self
    {
        $maxExecutionTime = (int) ini_get('max_execution_time');
        if ($maxExecutionTime > 0) {
            $seconds = min($seconds, max(0, $maxExecutionTime - (int) ceil($safetyMargin)));
        }

        return new self((float) $seconds);
    }

    public function remaining(): float
    {
        return max(0.0, $this->deadline - microtime(true));
    }

    public function expired(): bool
    {
        return $this->remaining() <= 0.0;
    }
}

