<?php

namespace App\Services\Orchestrator;

use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\DomainModelSelector;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Project\ProjectService;

class SecurityAuditorService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback,
        protected ProjectService $projects,
        protected AgentPersonaService $personas,
        protected DomainModelSelector $modelSelector,
    ) {}

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $executorResult
     * @return array<string, mixed>
     */
    /**
     * @param  list<array<string, mixed>>  $preflightReads
     */
    public function audit(
        string $userPrompt,
        array $route,
        array $plan,
        array $executorResult,
        ?string $runId = null,
        array $preflightReads = [],
    ): array {
        $toolEvidence = ExecutorEvidenceSupport::toolEvidenceForRun($runId);
        $previewMax = (int) config('bossku.security_audit_preview_max_files', 10);
        $executorPayload = ExecutorEvidenceSupport::executorPayloadForAudit(
            $executorResult,
            $preflightReads,
            $runId,
            $previewMax,
        );

        if (! ExecutorEvidenceSupport::hasReadEvidence($executorResult, $toolEvidence)) {
            return ExecutorEvidenceSupport::deterministicNoFilesRead();
        }

        if (
            ExecutorEvidenceSupport::countFilesRead($executorResult) > 0
            && ($executorPayload['read_previews'] ?? []) === []
        ) {
            return ExecutorEvidenceSupport::deterministicNoReadableContent();
        }

        $cfg = $this->config->securityAuditor();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        // Security review is inherently a deep-reasoning domain regardless of the change.
        $models = $this->modelSelector->order(
            $models,
            'security',
            $this->config->roleModelIsPinned('security_auditor'),
        );
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
            'executor' => $executorPayload,
            'tool_evidence' => $toolEvidence,
        ], JSON_THROW_ON_ERROR);

        $handoffMessage = StringCoercion::toString($executorResult['handoff_message'] ?? null, 'Security audit handoff from auditor pipeline.');
        $userContent = $this->personas->wrapHandoffUserContent('security_auditor', 'auditor', $handoffMessage, $payload);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
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
            (int) ($cfg['max_tokens'] ?? 12000),
            $runId,
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return LlmTelemetry::mergeAgentResult(array_merge($parsed, [
            '_model_used' => $out['model_used'],
            '_model_resolved' => $out['model_resolved'] ?? '',
            '_fallback_used' => $out['fallback_used'],
        ]), $out);
    }
}
