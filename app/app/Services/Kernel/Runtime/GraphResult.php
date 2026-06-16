<?php

namespace App\Services\Kernel\Runtime;

use App\Services\Kernel\Constants;
use App\Services\Kernel\Types\Interrupt;

/**
 * Outcome of a graph run/resume: terminal status, the final channel values, the
 * interrupt payload (if suspended), the last checkpoint id, and step count.
 */
final class GraphResult
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public readonly string $status,
        public readonly array $values,
        public readonly ?Interrupt $interrupt = null,
        public readonly ?string $checkpointId = null,
        public readonly int $steps = 0,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === Constants::STATUS_COMPLETED;
    }

    public function isInterrupted(): bool
    {
        return $this->status === Constants::STATUS_INTERRUPTED;
    }
}
