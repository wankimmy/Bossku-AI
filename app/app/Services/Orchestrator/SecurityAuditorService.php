<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Project\ProjectService;

class SecurityAuditorService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback,
        protected ProjectService $projects
    ) {}

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $executorResult
     * @return array<string, mixed>
     */
    public function audit(
        string $userPrompt,
        array $route,
        array $plan,
        array $executorResult,
        ?string $runId = null,
    ): array {
        $toolEvidence = ExecutorEvidenceSupport::toolEvidenceForRun($runId);

        if (! ExecutorEvidenceSupport::hasReadEvidence($executorResult, $toolEvidence)) {
            return ExecutorEvidenceSupport::deterministicNoFilesRead();
        }

        $cfg = $this->config->securityAuditor();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $system = <<<'SYS'
You are BosskuAI security auditor. Output ONLY valid JSON:
status ("pass"|"revise"|"reject"),
summary (string),
security_issues (array of {severity: "low"|"medium"|"high"|"critical", issue: string, recommendation: string}).
The payload includes executor.files_read and tool_evidence from real file_read_safe / file_search operations.
If files_read or tool_evidence is non-empty, perform security analysis using only those paths.
Return status "revise" with summary explaining no_files_read only when both files_read and tool_evidence are empty arrays.
SYS;
        $system .= "\n\n".$this->projects->evidenceRuleForPrompt();

        $payload = json_encode([
            'user_prompt' => $userPrompt,
            'route' => $route,
            'plan_summary' => $plan['summary'] ?? null,
            'target_files' => $plan['target_file_list'] ?? [],
            'executor' => ExecutorEvidenceSupport::executorPayloadForAudit($executorResult),
            'tool_evidence' => $toolEvidence,
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
            'security_auditor',
            function (mixed $j): bool {
                return is_array($j) && isset($j['status'], $j['summary']);
            },
            (int) ($cfg['max_tokens'] ?? 12000)
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return array_merge($parsed, [
            '_model_used' => $out['model_used'],
            '_model_resolved' => $out['model_resolved'] ?? '',
            '_fallback_used' => $out['fallback_used'],
        ]);
    }
}
