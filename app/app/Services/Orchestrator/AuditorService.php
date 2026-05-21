<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Project\ProjectService;

class AuditorService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected ProjectService $projects
    ) {}

    /**
     * @param  array<string, mixed>  $router
     * @param  array<string, mixed>  $planner
     * @param  array<string, mixed>  $executorResult
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
        bool $highRiskContext
    ): array {
        $cfg = $this->modelConfig->auditor();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $system = <<<'SYS'
You are the BosskuAI auditor. Output ONLY valid JSON with keys:
status ("pass"|"pass_with_notes"|"needs_revision"|"failed"),
summary (string),
findings (array of {id: string, severity: "low"|"medium"|"high"|"critical", category: "correctness"|"security"|"performance"|"maintainability"|"tests", title: string, description: string, suggested_fix: string, status: "open"|"fixed"|"accepted_risk"}),
required_fixes (string[]),
optional_improvements (string[]),
risk_level ("low"|"medium"|"high"),
requires_security_audit (boolean),
requires_final_reviewer (boolean),
final_output (string, user-facing summary when status is pass or pass_with_notes).
Use needs_revision only when executor should make another focused pass.
Do not report specific file paths or vulnerabilities unless executor tool results include file_read_safe or file_search for those files.
SYS;
        $system .= "\n\n".$this->projects->evidenceRuleForPrompt();

        $payload = json_encode([
            'user_prompt' => $userPrompt,
            'skill_router' => $router,
            'model_route' => $modelRoute,
            'plan_summary' => $planner['summary'] ?? null,
            'target_files' => $planner['target_file_list'] ?? [],
            'step' => $step,
            'executor' => $executorResult,
            'rules' => $ruleLines,
            'checklist_excerpt' => $checklistExcerpt,
            'high_risk_context' => $highRiskContext,
        ], JSON_THROW_ON_ERROR);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $payload],
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
        $parsed = $this->normalizeAudit($parsed, $highRiskContext);
        $legacyPass = in_array(($parsed['status'] ?? ''), ['pass', 'pass_with_notes'], true);

        return array_merge($parsed, [
            '_legacy_pass' => $legacyPass,
            '_auditor_model' => $out['model_used'],
            '_auditor_model_resolved' => $out['model_resolved'] ?? '',
        ]);
    }

    /** @param array<string, mixed> $parsed */
    protected function normalizeAudit(array $parsed, bool $highRiskContext): array
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
                'severity' => (string) ($issue['severity'] ?? 'medium'),
                'category' => 'correctness',
                'title' => (string) ($issue['issue'] ?? 'Audit issue'),
                'description' => (string) ($issue['issue'] ?? ''),
                'suggested_fix' => (string) ($issue['recommendation'] ?? ''),
                'status' => 'open',
            ], $issues, array_keys($issues));
        }

        return [
            'status' => in_array($status, ['pass', 'pass_with_notes', 'needs_revision', 'failed'], true) ? $status : 'failed',
            'summary' => (string) ($parsed['summary'] ?? 'Audit completed.'),
            'findings' => array_values($findings),
            'required_fixes' => is_array($parsed['required_fixes'] ?? null) ? $parsed['required_fixes'] : [],
            'optional_improvements' => is_array($parsed['optional_improvements'] ?? null) ? $parsed['optional_improvements'] : [],
            'risk_level' => (string) ($parsed['risk_level'] ?? ($highRiskContext ? 'high' : 'medium')),
            'requires_security_audit' => (bool) ($parsed['requires_security_audit'] ?? $highRiskContext),
            'requires_final_reviewer' => (bool) ($parsed['requires_final_reviewer'] ?? $highRiskContext),
            'final_output' => (string) ($parsed['final_output'] ?? $parsed['summary'] ?? ''),
        ];
    }
}
