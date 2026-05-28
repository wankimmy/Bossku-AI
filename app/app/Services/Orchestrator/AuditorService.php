<?php

namespace App\Services\Orchestrator;

use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Project\ProjectService;

class AuditorService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected ProjectService $projects,
        protected AgentPersonaService $personas
    ) {}

    /**
     * @param  array<string, mixed>  $router
     * @param  array<string, mixed>  $planner
     * @param  array<string, mixed>  $executorResult
     * @param  list<array<string, mixed>>  $preflightReads
     * @param  list<string>  $ruleLines
     * @return array<string, mixed>
     */
    public function auditStep(
        string $userPrompt,
        array $router,
        array $modelRoute,
        array $planner,
        array $step,
        array $executorResult,
        array $ruleLines,
        string $checklistExcerpt,
        bool $highRiskContext,
        array $preflightReads = [],
        ?string $runId = null,
        array $memoryContext = [],
        array $conversation = [],
    ): array {
        $cfg = $this->modelConfig->auditor();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $memBlock = $this->buildMemoryBlock($memoryContext);
        $conversationBlock = $this->buildConversationBlock($conversation);
        $plannerQuestions = is_array($planner['planner_questions'] ?? null) && $planner['planner_questions'] !== []
            ? 'The Planner surfaced these unresolved questions: '.json_encode($planner['planner_questions'])
            : '';

        // Deterministic pre-check: find checklist items the executor never reported on
        $planChecklist = is_array($planner['checklist'] ?? null) ? $planner['checklist'] : [];
        $execChecklistStatus = is_array($executorResult['checklist_status'] ?? null) ? $executorResult['checklist_status'] : [];
        $reportedIds = array_column($execChecklistStatus, 'id');
        $missingItems = array_values(array_filter($planChecklist, fn ($item) => ! in_array($item['id'] ?? '', $reportedIds, true)));
        $missingNote = $missingItems !== []
            ? "\nDETERMINISTIC FINDING: The following ".count($missingItems).' checklist item(s) were NEVER reported in executor_checklist_status: '
                .implode(', ', array_map(fn ($item) => '['.$item['id'].'] '.$item['title'], $missingItems))
                .'. Mark these as "unverifiable" in verdict_trail and flag status=needs_revision unless the executor evidence clearly covers them.'
            : '';

        $system = <<<SYS
You are the BosskuAI Auditor — Stage 3 of 3 in a three-stage pipeline (Planner → Executor → Auditor).
{$missingNote}

PIPELINE CONTEXT:
- The Planner (Stage 1) produced a concrete plan with a checklist, confidence score, and risk notes.
- The Executor (Stage 2) implemented the plan and provided a checklist_status with per-item completion reports.
- Your job is to adversarially verify BOTH: (a) individual code quality, AND (b) whether the Executor actually completed every checklist item it claimed.

Your role: Be skeptical. Demand evidence. Catch mistakes, not validate them. Surface memory conflicts and checklist deviations.

CRITICAL RULES:
- Every finding MUST cite file:line evidence from executor files_read, files_changed, read_previews, or tool_evidence. No speculative findings.
- Compare Executor output against prior memory context — if the Executor repeated a known past mistake, flag it as a memory conflict.
- If the Executor claimed to change files but provided no after/diff evidence, flag status=needs_revision.
- Set needs_user_input ONLY for high-risk concerns (security, data loss, irreversible architecture) the user has not already approved — not for routine fixes.
- Use needs_revision when the Executor should make another focused pass without user approval.
- {$plannerQuestions}

PER-CHECKLIST VERIFICATION (mandatory — you MUST do this):
1. For each item in `plan_checklist`, find the matching entry in `executor_checklist_status` (match by `id`).
2. If executor says "completed", verify there is concrete evidence in files_changed or files_read that supports the claim. If no evidence, your verdict is "disputed".
3. If executor says "partial" or "failed", investigate why and whether a revision would fix it.
4. If no matching entry in executor_checklist_status, verdict is "unverifiable".
5. Record your per-item verdict in `verdict_trail` — one entry per checklist item.

CONVERSATION HISTORY (use to understand prior attempts and user intent):
{$conversationBlock}

Prior memory context (lessons from past runs — check if Executor ignored or repeated known issues):
{$memBlock}

Output ONLY valid JSON with keys:
status ("pass"|"pass_with_notes"|"needs_revision"|"failed"),
summary (string),
findings (array of {id: string, severity: "low"|"medium"|"high"|"critical", category: "functionality"|"correctness"|"design"|"maintainability"|"performance"|"security"|"tests", title: string, description: string, suggested_fix: string, status: "open"|"fixed"|"accepted_risk"}),
required_fixes (string[]),
optional_improvements (string[]),
risk_level ("low"|"medium"|"high"),
requires_security_audit (boolean),
requires_final_reviewer (boolean),
final_output (string, user-facing summary when status is pass or pass_with_notes),
needs_user_input (boolean),
questions (array of {id: string, prompt: string, options?: array of {id: string, label: string}}),
blockers (string[]),
memory_conflicts (string[] — list any cases where Executor repeated a known past mistake from memory context; empty if none),
verdict_trail (array of {id: string, plan_title: string, executor_status: string, auditor_verdict: "verified"|"disputed"|"unverifiable", evidence: string, notes: string} — one entry per plan checklist item),
user_questions (array of {id: string, question: string, risk: "low"|"medium"|"high"|"critical", why: string} — ONLY for irreversible or high-stakes decisions requiring explicit user approval; empty for routine findings).

If executor.status is "failed" but read_previews are present, review that evidence only — do not invent code changes or request a re-run for JSON/schema errors alone.
SYS;
        $system .= "\n\n".$this->projects->evidenceRuleForPrompt();
        $system .= "\n\n".$this->fullAuditInstructions($modelRoute);

        $executorPayload = ExecutorEvidenceSupport::executorPayloadForAudit($executorResult, $preflightReads, $runId);

        $payload = json_encode([
            'user_prompt' => $userPrompt,
            'skill_router' => $router,
            'model_route' => $modelRoute,
            'plan_summary' => $planner['summary'] ?? null,
            'plan_confidence' => $planner['confidence'] ?? null,
            'plan_checklist' => $planner['checklist'] ?? [],
            'planner_questions' => $planner['planner_questions'] ?? [],
            'memory_applied_by_planner' => $planner['memory_applied'] ?? [],
            'target_files' => $planner['target_file_list'] ?? [],
            'step' => $step,
            'executor' => $executorPayload,
            'executor_checklist_status' => $executorResult['checklist_status'] ?? [],
            'executor_memory_lessons_applied' => $executorResult['memory_lessons_applied'] ?? [],
            'rules' => $ruleLines,
            'checklist_excerpt' => $checklistExcerpt,
            'high_risk_context' => $highRiskContext,
            'memory_context_count' => count($memoryContext),
            'conversation_turns' => count($conversation),
            'missing_checklist_items' => array_column($missingItems, 'id'),
        ], JSON_THROW_ON_ERROR);

        $handoffMessage = StringCoercion::toString($executorResult['handoff_message'] ?? null, 'Sending changes to Auditor.');
        $userContent = $this->personas->wrapHandoffUserContent('auditor', 'executor', $handoffMessage, $payload);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];

        $out = $this->fallback->chatWithFallbacks(
            $models,
            $messages,
            (float) ($cfg['temperature'] ?? 0.1),
            $retry,
            'auditor',
            function (mixed $j): bool {
                return is_array($j) && isset($j['status'], $j['summary']);
            },
            (int) ($cfg['max_tokens'] ?? 6000),
            $runId,
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        // Map to legacy pass/fail for callers
        $parsed = $this->normalizeAudit($parsed, $highRiskContext, $modelRoute);
        $legacyPass = in_array(($parsed['status'] ?? ''), ['pass', 'pass_with_notes'], true);

        return LlmTelemetry::mergeAgentResult(array_merge($parsed, [
            '_legacy_pass' => $legacyPass,
            '_auditor_model' => $out['model_used'],
            '_auditor_model_resolved' => $out['model_resolved'] ?? '',
        ]), $out);
    }

    protected function fullAuditInstructions(array $modelRoute): string
    {
        if (($modelRoute['audit_mode'] ?? '') !== 'full') {
            return '';
        }

        return <<<'SYS'
FULL REPOSITORY AUDIT MODE — Review executor evidence in this order and tag each finding with the matching category:
1) Functionality (functionality or correctness): features, routes/APIs, business logic, edge cases, broken flows.
2) Design & best practices (design or maintainability): structure, naming, duplication, conventions, API design.
3) Performance (performance): N+1 queries, caching, indexes, memory, slow loops, bundle size.
4) Tests (tests): missing coverage, weak assertions, untested critical paths.
For dimensions 1–3, include at least one finding OR state explicitly in summary that no issues were found in the evidence read.
Set requires_security_audit to true so a dedicated security auditor runs after this pass (do not duplicate deep OWASP work here).
SYS;
    }

    /** @param array<string, mixed> $parsed */
    /** @param array<string, mixed> $modelRoute */
    protected function normalizeAudit(array $parsed, bool $highRiskContext, array $modelRoute = []): array
    {
        $status = (string) ($parsed['status'] ?? 'failed');
        $status = match ($status) {
            'revise' => 'needs_revision',
            'reject' => 'failed',
            default => $status,
        };

        $issues = is_array($parsed['issues'] ?? null) ? $parsed['issues'] : [];
        $findings = is_array($parsed['findings'] ?? null) ? $parsed['findings'] : [];
        if ($findings === [] && $issues !== []) {
            $findings = array_map(fn ($issue, $idx) => [
                'id' => 'audit-'.($idx + 1),
                'severity' => StringCoercion::toString($issue['severity'] ?? null, 'medium'),
                'category' => 'correctness',
                'title' => StringCoercion::toString($issue['issue'] ?? null, 'Audit issue'),
                'description' => StringCoercion::toString($issue['issue'] ?? null),
                'suggested_fix' => StringCoercion::toString($issue['recommendation'] ?? null),
                'status' => 'open',
            ], $issues, array_keys($issues));
        }

        return [
            'status' => in_array($status, ['pass', 'pass_with_notes', 'needs_revision', 'failed'], true) ? $status : 'failed',
            'summary' => StringCoercion::toString($parsed['summary'] ?? null, 'Audit completed.'),
            'findings' => array_values($findings),
            'required_fixes' => is_array($parsed['required_fixes'] ?? null) ? $parsed['required_fixes'] : [],
            'optional_improvements' => is_array($parsed['optional_improvements'] ?? null) ? $parsed['optional_improvements'] : [],
            'risk_level' => StringCoercion::toString($parsed['risk_level'] ?? null, $highRiskContext ? 'high' : 'medium'),
            'requires_security_audit' => (bool) ($parsed['requires_security_audit'] ?? $highRiskContext)
                || (($modelRoute['audit_mode'] ?? '') === 'full'),
            'requires_final_reviewer' => (bool) ($parsed['requires_final_reviewer'] ?? $highRiskContext),
            'final_output' => StringCoercion::toString($parsed['final_output'] ?? $parsed['summary'] ?? null),
            'needs_user_input' => (bool) ($parsed['needs_user_input'] ?? false),
            'questions' => is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [],
            'blockers' => is_array($parsed['blockers'] ?? null) ? $parsed['blockers'] : [],
            'memory_conflicts' => is_array($parsed['memory_conflicts'] ?? null) ? $parsed['memory_conflicts'] : [],
            'verdict_trail' => is_array($parsed['verdict_trail'] ?? null) ? $parsed['verdict_trail'] : [],
            'user_questions' => is_array($parsed['user_questions'] ?? null) ? $parsed['user_questions'] : [],
        ];
    }

    /** @param list<array{role: string, content: string}> $conversation */
    protected function buildConversationBlock(array $conversation): string
    {
        if ($conversation === []) {
            return '(no prior conversation — this is the first turn)';
        }
        $total = count($conversation);
        $recent = array_slice($conversation, -10);
        $offset = max(0, $total - 10);
        $lines = [];
        foreach ($recent as $idx => $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $cap = $role === 'assistant' ? 1200 : 800;
            $content = mb_substr((string) ($turn['content'] ?? ''), 0, $cap);
            $lines[] = '[Turn '.($offset + $idx).'] '.strtoupper($role).': '.$content;
        }

        return implode("\n\n", $lines);
    }

    /** @param list<array<string,mixed>> $memories */
    protected function buildMemoryBlock(array $memories): string
    {
        if ($memories === []) {
            return '(no prior memory retrieved)';
        }
        $lines = [];
        foreach ($memories as $i => $m) {
            $summary = is_array($m) ? ($m['summary'] ?? $m['content'] ?? '') : (string) $m;
            $lines[] = '[Memory '.($i + 1).'] '.$summary;
        }

        return implode("\n", $lines);
    }
}
