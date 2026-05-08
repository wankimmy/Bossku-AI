<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class AuditorService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig
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
status ("pass"|"revise"|"reject"),
summary (string),
issues (array of {severity: "low"|"medium"|"high", file: string, issue: string, recommendation: string}),
requires_security_audit (boolean),
requires_final_reviewer (boolean),
final_output (string, user-facing summary when status is pass).
SYS;

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
        $legacyPass = (($parsed['status'] ?? '') === 'pass');

        return array_merge($parsed, [
            '_legacy_pass' => $legacyPass,
            '_auditor_model' => $out['model_used'],
            '_auditor_model_resolved' => $out['model_resolved'] ?? '',
        ]);
    }
}
