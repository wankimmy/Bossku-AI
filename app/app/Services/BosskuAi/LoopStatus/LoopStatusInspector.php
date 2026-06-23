<?php

namespace App\Services\BosskuAi\LoopStatus;

/**
 * Inspects run step logs for signs of a stuck loop. Ported from ECC's
 * loop-status.js (scripts/loop-status.js). Detects:
 * - repeated identical tool calls (stuck on the same failure)
 * - overdue wakeups (a step started but never completed)
 * - parse errors in agent output (JSON decode failures)
 * - max-iterations reached without completion
 *
 * The inspector is read-only; it reports findings, it does not fix them.
 * Usage: LoopStatusInspector::inspect($runSteps) → LoopStatusReport.
 */
final class LoopStatusInspector
{
    /**
     * @param  list<array<string, mixed>>  $steps  run steps from the DB
     */
    public function inspect(array $steps): LoopStatusReport
    {
        $findings = [];
        $stuckSignatures = [];
        $overdueSteps = [];
        $parseErrors = [];
        $maxIterationsReached = false;

        foreach ($steps as $step) {
            $agent = (string) ($step['agent'] ?? '');
            $status = (string) ($step['status'] ?? '');
            $output = (string) ($step['output'] ?? '');

            // Detect repeated identical tool calls (stuck detection).
            $sig = md5($agent.'|'.$output);
            if (in_array($sig, $stuckSignatures, true)) {
                $findings[] = [
                    'type' => 'repeated_tool_call',
                    'agent' => $agent,
                    'step' => $step['step_number'] ?? null,
                    'severity' => 'warning',
                    'message' => "Agent '{$agent}' repeated an identical output — possible stuck loop.",
                ];
            }
            $stuckSignatures[] = $sig;
            $stuckSignatures = array_slice($stuckSignatures, -10);

            // Detect parse errors (JSON decode failures in output).
            if (str_contains($output, 'json_decode_error') || str_contains($output, 'JSON parse failed')) {
                $parseErrors[] = [
                    'agent' => $agent,
                    'step' => $step['step_number'] ?? null,
                    'message' => 'Output contains a JSON parse error.',
                ];
            }

            // Detect steps that started but never completed.
            if ($status === 'running') {
                $startedAt = $step['created_at'] ?? null;
                if ($startedAt !== null) {
                    $overdueSteps[] = [
                        'agent' => $agent,
                        'step' => $step['step_number'] ?? null,
                        'started_at' => $startedAt,
                        'message' => "Agent '{$agent}' step is still running — possible overdue wakeup.",
                    ];
                }
            }

            // Detect max iterations reached.
            if (str_contains($output, 'max_iterations') || str_contains($output, 'iteration cap reached')) {
                $maxIterationsReached = true;
                $findings[] = [
                    'type' => 'max_iterations',
                    'agent' => $agent,
                    'step' => $step['step_number'] ?? null,
                    'severity' => 'critical',
                    'message' => "Agent '{$agent}' hit the iteration cap without completing.",
                ];
            }
        }

        $healthy = $findings === [] && $parseErrors === [] && $overdueSteps === [];

        return new LoopStatusReport(
            healthy: $healthy,
            findings: $findings,
            parseErrors: $parseErrors,
            overdueSteps: $overdueSteps,
            maxIterationsReached: $maxIterationsReached,
            stepCount: count($steps),
        );
    }
}