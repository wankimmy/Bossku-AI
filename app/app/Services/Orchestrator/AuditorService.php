<?php

namespace App\Services\Orchestrator;

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
    ): array {
        $cfg = $this->modelConfig->auditor();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $system = <<<'SYS'
You are the BosskuAI auditor. Output ONLY valid JSON with keys:
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
blockers (string[]).
Use needs_revision only when executor should make another focused pass without user approval.
Set needs_user_input true ONLY for high-risk concerns (security, data loss, irreversible architecture) the user has not already approved — not for routine fixes.
Every finding MUST cite file:line evidence from executor files_read, files_changed, read_previews, or tool_evidence — no speculative findings.
Do not report specific file paths or vulnerabilities unless executor tool results include file_read_safe or file_search for those files.
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
            'target_files' => $planner['target_file_list'] ?? [],
            'step' => $step,
            'executor' => $executorPayload,
            'rules' => $ruleLines,
            'checklist_excerpt' => $checklistExcerpt,
            'high_risk_context' => $highRiskContext,
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
            (int) ($cfg['max_tokens'] ?? 10000)
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        // Map to legacy pass/fail for callers
        $parsed = $this->normalizeAudit($parsed, $highRiskContext, $modelRoute);
        $legacyPass = in_array(($parsed['status'] ?? ''), ['pass', 'pass_with_notes'], true);

        return array_merge($parsed, [
            '_legacy_pass' => $legacyPass,
            '_auditor_model' => $out['model_used'],
            '_auditor_model_resolved' => $out['model_resolved'] ?? '',
        ]);
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
        ];
    }
}
