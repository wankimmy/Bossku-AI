<?php

namespace App\Services\BosskuAi\LoopStatus;

/**
 * The result of inspecting a run's steps for loop-health issues. Ported from
 * ECC's loop-status report. The report is read-only — it surfaces problems,
 * it does not fix them. Callers (the loop-operator or the orchestrator) decide
 * what to do: run introspection, re-architect the loop, or escalate.
 */
final readonly class LoopStatusReport
{
    /**
     * @param  bool  $healthy  true if no findings, parse errors, or overdue steps
     * @param  list<array<string, mixed>>  $findings  repeated calls + max-iterations
     * @param  list<array<string, mixed>>  $parseErrors  JSON decode failures
     * @param  list<array<string, mixed>>  $overdueSteps  steps still in 'running' state
     * @param  bool  $maxIterationsReached  any step hit the iteration cap
     * @param  int  $stepCount  total steps inspected
     */
    public function __construct(
        public bool $healthy,
        public array $findings,
        public array $parseErrors,
        public array $overdueSteps,
        public bool $maxIterationsReached,
        public int $stepCount,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'healthy' => $this->healthy,
            'findings' => $this->findings,
            'parse_errors' => $this->parseErrors,
            'overdue_steps' => $this->overdueSteps,
            'max_iterations_reached' => $this->maxIterationsReached,
            'step_count' => $this->stepCount,
        ];
    }
}