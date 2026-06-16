<?php

namespace App\Services\Kernel\Types;

/**
 * Declarative per-node timeout. Native PHP cannot preempt a synchronous call
 * without pcntl/queue workers, so the runner enforces this best-effort:
 * post-hoc — if a node's wall-clock duration exceeds the budget, the runner
 * raises a timeout (which a RetryPolicy may catch). Hard preemption arrives with
 * queue-worker execution. A node may also cooperatively check ctx for its budget.
 */
final class TimeoutPolicy
{
    public function __construct(public readonly float $seconds) {}

    public function exceeded(float $elapsedSeconds): bool
    {
        return $this->seconds > 0 && $elapsedSeconds > $this->seconds;
    }
}
