<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class FinalReviewerService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback,
        protected AgentPersonaService $personas
    ) {}

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $auditor
     * @param  array<string, mixed>|null  $securityAudit
     * @param  array<string, mixed>  $executorResult
     * @return array<string, mixed>
     */
    public function review(
        string $userPrompt,
        array $route,
        array $auditor,
        ?array $securityAudit,
        array $executorResult
    ): array {
        $cfg = $this->config->finalReviewer();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $system = <<<'SYS'
You are BosskuAI final reviewer. Do not write code. Output ONLY valid JSON:
decision ("MERGE"|"REVISE"|"REJECT"),
reason (string),
required_actions (string[]).
SYS;

        $payload = json_encode([
            'user_prompt' => $userPrompt,
            'route' => $route,
            'auditor' => $auditor,
            'security_audit' => $securityAudit,
            'patch_summary' => $executorResult['patch_summary'] ?? '',
            'tests_result' => $executorResult['tests_result'] ?? 'not_run',
            'files_changed' => $executorResult['files_changed'] ?? [],
        ], JSON_THROW_ON_ERROR);

        $fromRole = $securityAudit !== null ? 'security_auditor' : 'auditor';
        $handoffMessage = (string) ($auditor['summary'] ?? 'Final review handoff.');
        $userContent = $this->personas->wrapHandoffUserContent('final_reviewer', $fromRole, $handoffMessage, $payload);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];

        $out = $this->fallback->chatWithFallbacks(
            $models,
            $messages,
            (float) ($cfg['temperature'] ?? 0.1),
            $retry,
            'final_reviewer',
            function (mixed $j): bool {
                return is_array($j) && isset($j['decision'], $j['reason']);
            },
            (int) ($cfg['max_tokens'] ?? 8000)
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return array_merge($parsed, [
            '_model_used' => $out['model_used'],
            '_model_resolved' => $out['model_resolved'] ?? '',
        ]);
    }
}
