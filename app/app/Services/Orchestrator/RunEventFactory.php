<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Run;

class RunEventFactory
{
    /** @return array<string, mixed> */
    public function plannerDone(Run $run, array $plan, string $model, int $latencyMs, int $tokenEstimate): array
    {
        return $this->event($run, 'planner_done', [
            'agent' => 'orchestrator',
            'from_agent' => 'orchestrator',
            'to_agent' => 'executor',
            'status' => 'success',
            'model_role' => 'reasoning',
            'model' => $model,
            'summary' => 'Planner created '.count($plan['checklist'] ?? []).'-step execution checklist.',
            'message' => (string) ($plan['task_summary'] ?? $plan['summary'] ?? 'Planner completed.'),
            'artifacts' => [
                'plan' => $plan,
                'checklist' => $plan['checklist'] ?? [],
            ],
            'latency_ms' => $latencyMs,
            'token_estimate' => $tokenEstimate,
            'output' => json_encode($plan) ?: '',
        ]);
    }

    /** @return array<string, mixed> */
    public function executorDone(Run $run, array $result, string $model, int $latencyMs, int $tokenEstimate, string $type = 'executor_step_done'): array
    {
        $revision = $type === 'executor_revision_done';

        return $this->event($run, $type, [
            'agent' => 'executor',
            'from_agent' => 'executor',
            'to_agent' => 'auditor',
            'status' => ($result['status'] ?? '') === 'failed' ? 'fail' : 'success',
            'model_role' => 'coding',
            'model' => $model,
            'summary' => $revision ? 'Executor applied audit follow-up fixes.' : 'Executor completed the requested changes.',
            'message' => (string) ($result['patch_summary'] ?? ''),
            'artifacts' => $this->executorArtifacts($result),
            'latency_ms' => $latencyMs,
            'token_estimate' => $tokenEstimate,
            'output' => $result['patch_summary'] ?? json_encode($result),
        ]);
    }

    /** @return array<string, mixed> */
    public function auditorDone(Run $run, array $audit, string $model, int $latencyMs, int $tokenEstimate): array
    {
        $status = (string) ($audit['status'] ?? 'failed');

        return $this->event($run, 'auditor_done', [
            'agent' => 'auditor',
            'from_agent' => 'auditor',
            'to_agent' => $status === 'needs_revision' ? 'executor' : 'final-reviewer',
            'status' => $status === 'needs_revision' ? 'needs_revision' : (($audit['_legacy_pass'] ?? false) ? 'success' : 'fail'),
            'model_role' => 'review',
            'model' => $model,
            'summary' => (string) ($audit['summary'] ?? 'Audit completed.'),
            'message' => $status === 'needs_revision' ? 'Returning feedback to Executor.' : 'Sending audit result to Final Reviewer.',
            'artifacts' => [
                'audit' => $audit,
                'audit_findings' => $audit['findings'] ?? [],
            ],
            'latency_ms' => $latencyMs,
            'token_estimate' => $tokenEstimate,
            'output' => json_encode($audit) ?: '',
        ]);
    }

    /** @return array<string, mixed> */
    public function finalReviewerDone(Run $run, array $review, ?string $model, int $latencyMs, int $tokenEstimate): array
    {
        return $this->event($run, 'final_reviewer_done', [
            'agent' => 'final-reviewer',
            'from_agent' => 'final-reviewer',
            'to_agent' => 'system',
            'status' => 'success',
            'model_role' => 'reasoning',
            'model' => $model,
            'summary' => (string) ($review['summary'] ?? $review['reason'] ?? 'Final review completed.'),
            'message' => (string) ($review['reason'] ?? ''),
            'artifacts' => [
                'final_review' => $review,
            ],
            'latency_ms' => $latencyMs,
            'token_estimate' => $tokenEstimate,
            'output' => json_encode($review) ?: '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @return array<string, mixed>
     */
    public function runCompleted(
        Run $run,
        string $output,
        int $totalMs,
        int $tokenEstimate,
        array $modelRoute = [],
        array $modelsResolved = [],
    ): array {
        return $this->event($run, 'run_completed', [
            'agent' => 'final-reviewer',
            'from_agent' => 'final-reviewer',
            'to_agent' => 'system',
            'status' => 'success',
            'model_role' => 'reasoning',
            'model' => $modelsResolved['final_reviewer'] ?? $modelsResolved['orchestrator'] ?? null,
            'summary' => 'Run completed.',
            'message' => 'Final result is ready.',
            'routing' => $modelRoute,
            'models' => $modelsResolved,
            'artifacts' => [
                'final_output' => $output,
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
            ],
            'total_latency_ms' => $totalMs,
            'latency_ms' => $totalMs,
            'token_estimate' => $tokenEstimate,
            'output' => $output,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    public function clarificationRequested(
        Run $run,
        array $questions,
        string $stage,
        string $summary,
        array $assumptions = [],
    ): array {
        return $this->event($run, 'clarification_requested', [
            'agent' => 'orchestrator',
            'from_agent' => 'orchestrator',
            'to_agent' => 'user',
            'status' => 'awaiting_input',
            'model_role' => 'reasoning',
            'summary' => $summary,
            'message' => $summary,
            'stage' => $stage,
            'questions' => $questions,
            'assumptions' => $assumptions,
            'artifacts' => [
                'clarification' => [
                    'stage' => $stage,
                    'questions' => $questions,
                    'assumptions' => $assumptions,
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function clarificationReceived(Run $run, int $answerCount): array
    {
        return $this->event($run, 'clarification_received', [
            'agent' => 'orchestrator',
            'status' => 'running',
            'summary' => 'Continuing run with '.$answerCount.' clarification answer(s).',
            'message' => 'Resuming pipeline after your input.',
        ]);
    }

    /** @return array<string, mixed> */
    public function event(Run $run, string $type, array $extras = []): array
    {
        $agent = (string) ($extras['agent'] ?? $this->agentForType($type));

        return array_merge([
            'run_id' => $run->id,
            'type' => $type,
            'agent' => $agent,
            'from_agent' => $extras['from_agent'] ?? $agent,
            'to_agent' => $extras['to_agent'] ?? null,
            'status' => $extras['status'] ?? 'success',
            'model_role' => $extras['model_role'] ?? $this->modelRoleForAgent($agent),
            'model' => $extras['model'] ?? null,
            'summary' => $extras['summary'] ?? null,
            'message' => $extras['message'] ?? null,
            'artifacts' => $extras['artifacts'] ?? [],
            'rules_used' => [],
            'playbooks_used' => [],
            'checklists_used' => [],
            'memory_used' => [],
        ], $extras);
    }

    /** @return array<string, mixed> */
    public function metadata(
        string $agent,
        string $modelRole,
        string $summary,
        string $message,
        array $artifacts = [],
        ?string $fromAgent = null,
        ?string $toAgent = null
    ): array {
        return [
            'agent' => $agent,
            'from_agent' => $fromAgent ?? $agent,
            'to_agent' => $toAgent,
            'model_role' => $modelRole,
            'summary' => $summary,
            'message' => $message,
            'artifacts' => $this->ensureArtifactShape($artifacts),
        ];
    }

    /** @return array<string, mixed> */
    public function executorArtifacts(array $result): array
    {
        return $this->ensureArtifactShape([
            'files_read' => $result['files_read'] ?? [],
            'files_changed' => $result['files_changed'] ?? [],
            'commands_run' => $result['commands_run'] ?? [],
            'tests_run' => $result['tests_run'] ?? [],
        ]);
    }

    /** @return array<string, mixed> */
    protected function ensureArtifactShape(array $artifacts): array
    {
        return array_merge([
            'plan' => [],
            'checklist' => [],
            'files_read' => [],
            'files_changed' => [],
            'commands_run' => [],
            'tests_run' => [],
            'audit_findings' => [],
        ], $artifacts);
    }

    protected function agentForType(string $type): string
    {
        return match (true) {
            str_contains($type, 'planner') => 'orchestrator',
            str_contains($type, 'executor') => 'executor',
            str_contains($type, 'security') => 'security-auditor',
            str_contains($type, 'auditor') => 'auditor',
            str_contains($type, 'final') || str_contains($type, 'completed') => 'final-reviewer',
            str_contains($type, 'router') => 'router',
            str_contains($type, 'memory') => 'memory',
            str_contains($type, 'clarification') => 'orchestrator',
            default => 'system',
        };
    }

    protected function modelRoleForAgent(string $agent): string
    {
        return match ($agent) {
            'orchestrator', 'final-reviewer' => 'reasoning',
            'executor' => 'coding',
            'auditor', 'security-auditor' => 'review',
            'router', 'memory' => 'fast',
            default => 'system',
        };
    }
}
