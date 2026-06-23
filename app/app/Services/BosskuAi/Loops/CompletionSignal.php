<?php

namespace App\Services\BosskuAi\Loops;

/**
 * Completion-signal mechanism for autonomous loops. Ported from ECC's
 * --completion-signal / --completion-threshold pattern. The agent outputs a
 * magic phrase when it believes the work is done; after N consecutive signals,
 * the loop stops.
 *
 * Usage:
 *   $signal = new CompletionSignal('BOSSKU_PROJECT_COMPLETE', 3);
 *   $signal->record('BOSSKU_PROJECT_COMPLETE'); // iteration 1
 *   $signal->record('BOSSKU_PROJECT_COMPLETE'); // iteration 2
 *   $signal->record('BOSSKU_PROJECT_COMPLETE'); // iteration 3
 *   $signal->shouldStop(); // true — threshold reached
 *
 * Any non-matching output resets the consecutive counter.
 */
final class CompletionSignal
{
    private int $consecutive = 0;

    /**
     * @param  string  $phrase  the magic phrase the agent outputs when done
     * @param  int  $threshold  consecutive signals required to stop (default 3)
     */
    public function __construct(
        public readonly string $phrase,
        public readonly int $threshold = 3,
    ) {
        if ($threshold < 1) {
            throw new \InvalidArgumentException('Completion threshold must be >= 1.');
        }
    }

    /**
     * Record the agent's output. If it contains the completion phrase,
     * increment the consecutive counter; otherwise reset it.
     */
    public function record(string $agentOutput): void
    {
        if (str_contains($agentOutput, $this->phrase)) {
            $this->consecutive++;
        } else {
            $this->consecutive = 0;
        }
    }

    /** Has the threshold been reached? */
    public function shouldStop(): bool
    {
        return $this->consecutive >= $this->threshold;
    }

    public function consecutiveCount(): int
    {
        return $this->consecutive;
    }

    public function reset(): void
    {
        $this->consecutive = 0;
    }
}