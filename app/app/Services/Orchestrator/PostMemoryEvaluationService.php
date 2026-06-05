<?php

namespace App\Services\Orchestrator;

class PostMemoryEvaluationService
{
    /**
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>  $learningResult
     * @return array<string, mixed>
     */
    public function evaluate(
        string $finalOutput,
        array $memPayload,
        array $execResult,
        array $lastAudit,
        array $learningResult,
        ?array $lastSecurity = null,
        ?array $lastFinal = null,
        array $modelRoute = [],
        array $modelsResolved = [],
    ): array {
        $responseScore = $this->scoreFinalResponse($finalOutput, $lastAudit, $lastFinal);
        $proofScore = $this->scoreProofCompleteness($execResult, $lastAudit, $lastSecurity);
        $memoryScore = $this->scoreMemoryQuality($memPayload, $learningResult, $modelRoute);

        $score = round(($responseScore * 0.45) + ($proofScore * 0.3) + ($memoryScore * 0.25), 2);
        $verdict = $score >= 0.8 ? 'pass' : ($score >= 0.6 ? 'needs_work' : 'fail');

        $dimensions = [
            [
                'id' => 'final_response',
                'label' => 'Final response',
                'weight' => 45,
                'score' => $responseScore,
                'note' => $this->finalResponseNote($finalOutput, $lastAudit, $lastFinal),
            ],
            [
                'id' => 'proof_completeness',
                'label' => 'Proof completeness',
                'weight' => 30,
                'score' => $proofScore,
                'note' => $this->proofNote($execResult, $lastAudit, $lastSecurity),
            ],
            [
                'id' => 'memory_quality',
                'label' => 'Memory quality',
                'weight' => 25,
                'score' => $memoryScore,
                'note' => $this->memoryNote($memPayload, $learningResult, $modelRoute),
            ],
        ];

        return [
            'agent' => 'evaluator',
            'stage' => 'post_memory_eval',
            'from_agent' => 'memory',
            'to_agent' => 'system',
            'status' => 'success',
            'verdict' => $verdict,
            'score' => $score,
            'summary' => $this->summaryForScore($score, $verdict, $finalOutput, $learningResult),
            'recommendation' => $this->recommendationForScore($score, $verdict, $dimensions),
            'dimensions' => $dimensions,
            'proof_summary' => $this->proofSummary($execResult, $lastAudit, $lastSecurity),
            'memory_summary' => $this->memorySummary($memPayload, $learningResult, $modelRoute),
            'models_resolved' => $modelsResolved,
        ];
    }

    /**
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastFinal
     */
    protected function scoreFinalResponse(string $finalOutput, array $lastAudit, ?array $lastFinal): float
    {
        $score = 0.25;
        $text = trim($finalOutput);

        if ($text !== '') {
            $score += 0.35;
            if (mb_strlen($text) > 80) {
                $score += 0.1;
            }
            if (preg_match('/\b(files?|tests?|memory|proof|audit|changes?)\b/i', $text)) {
                $score += 0.15;
            }
        }

        $auditStatus = (string) ($lastAudit['status'] ?? '');
        if (in_array($auditStatus, ['pass', 'pass_with_notes'], true)) {
            $score += 0.1;
        }

        if (is_array($lastFinal) && ($lastFinal['decision'] ?? '') === 'MERGE') {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     */
    protected function scoreProofCompleteness(array $execResult, array $lastAudit, ?array $lastSecurity): float
    {
        $checks = [];
        $checks[] = $this->hasItems($execResult['files_read'] ?? []);
        $checks[] = $this->hasItems($execResult['files_changed'] ?? []);
        $checks[] = $this->hasItems($execResult['commands_run'] ?? []) || $this->hasItems($execResult['_commands_executed'] ?? []);
        $checks[] = $this->hasItems($execResult['tests_run'] ?? []);
        $checks[] = $this->hasItems($lastAudit['findings'] ?? []);
        $checks[] = $lastSecurity !== null;

        return count($checks) > 0 ? round(array_sum(array_map(static fn (bool $v): int => $v ? 1 : 0, $checks)) / count($checks), 2) : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $learningResult
     * @param  array<string, mixed>  $modelRoute
     */
    protected function scoreMemoryQuality(array $memPayload, array $learningResult, array $modelRoute): float
    {
        $checks = [];
        $checks[] = $memPayload !== [];
        $checks[] = $learningResult !== [];
        $checks[] = in_array((string) ($modelRoute['memory_mode'] ?? 'read_only'), ['write_after_task', 'read_and_write'], true);
        $checks[] = $this->hasLearningSignal($learningResult);

        return count($checks) > 0 ? round(array_sum(array_map(static fn (bool $v): int => $v ? 1 : 0, $checks)) / count($checks), 2) : 0.0;
    }

    protected function hasItems(mixed $value): bool
    {
        return is_array($value) && $value !== [];
    }

    protected function hasLearningSignal(array $learningResult): bool
    {
        foreach (['summary', 'decision', 'items', 'saved', 'captured', 'outcome'] as $key) {
            if (array_key_exists($key, $learningResult) && $learningResult[$key] !== null && $learningResult[$key] !== false && $learningResult[$key] !== '') {
                return true;
            }
        }

        return $learningResult !== [];
    }

    /**
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastFinal
     */
    protected function finalResponseNote(string $finalOutput, array $lastAudit, ?array $lastFinal): string
    {
        if (trim($finalOutput) === '') {
            return 'Final output is missing.';
        }

        $status = (string) ($lastAudit['status'] ?? 'unknown');
        $decision = is_array($lastFinal) ? (string) ($lastFinal['decision'] ?? 'unknown') : 'unknown';

        return "Final output present; audit={$status}; final_review={$decision}.";
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     */
    protected function proofNote(array $execResult, array $lastAudit, ?array $lastSecurity): string
    {
        $filesRead = count(is_array($execResult['files_read'] ?? null) ? $execResult['files_read'] : []);
        $filesChanged = count(is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : []);
        $testsRun = count(is_array($execResult['tests_run'] ?? null) ? $execResult['tests_run'] : []);
        $auditFindings = count(is_array($lastAudit['findings'] ?? null) ? $lastAudit['findings'] : []);
        $security = $lastSecurity !== null ? 'security pass present' : 'no security pass';

        return "files_read={$filesRead}; files_changed={$filesChanged}; tests_run={$testsRun}; findings={$auditFindings}; {$security}.";
    }

    /**
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $learningResult
     * @param  array<string, mixed>  $modelRoute
     */
    protected function memoryNote(array $memPayload, array $learningResult, array $modelRoute): string
    {
        $learningKeys = implode(', ', array_slice(array_keys($learningResult), 0, 4));
        $mode = (string) ($modelRoute['memory_mode'] ?? 'read_only');

        return sprintf('memory_items=%d; mode=%s; learning_keys=%s.', count($memPayload), $mode, $learningKeys !== '' ? $learningKeys : 'none');
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     * @return array<string, int>
     */
    protected function proofSummary(array $execResult, array $lastAudit, ?array $lastSecurity): array
    {
        return [
            'files_read' => count(is_array($execResult['files_read'] ?? null) ? $execResult['files_read'] : []),
            'files_changed' => count(is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : []),
            'commands_run' => count(is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : []),
            'tests_run' => count(is_array($execResult['tests_run'] ?? null) ? $execResult['tests_run'] : []),
            'audit_findings' => count(is_array($lastAudit['findings'] ?? null) ? $lastAudit['findings'] : []),
            'security_pass' => $lastSecurity !== null ? 1 : 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $learningResult
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    protected function memorySummary(array $memPayload, array $learningResult, array $modelRoute): array
    {
        return [
            'memory_items' => count($memPayload),
            'learning_present' => $learningResult !== [],
            'memory_mode' => $modelRoute['memory_mode'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $dimensions
     */
    protected function recommendationForScore(float $score, string $verdict, array $dimensions): string
    {
        if ($verdict === 'fail') {
            return 'Repeat the run with stronger proof and memory capture.';
        }

        if ($verdict === 'needs_work') {
            $weak = collect($dimensions)->sortBy('score')->first();
            $label = is_array($weak) ? (string) ($weak['label'] ?? 'a weaker dimension') : 'a weaker dimension';

            return 'Improve '.$label.' before treating the run as production-ready.';
        }

        return 'Keep the current workflow and memory template.';
    }

    protected function summaryForScore(float $score, string $verdict, string $finalOutput, array $learningResult): string
    {
        $base = sprintf('Post-memory eval %s (%.2f).', $verdict, $score);
        if (trim($finalOutput) !== '') {
            return $base.' Final output was present.';
        }

        return $base.' Final output was empty.';
    }
}
