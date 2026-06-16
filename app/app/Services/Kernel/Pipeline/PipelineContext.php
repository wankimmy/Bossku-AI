<?php

namespace App\Services\Kernel\Pipeline;

/**
 * The assembled per-run context the pipeline services need (model route, memory,
 * skill, rules, etc.). Carried as a plain array in a RunState channel so it
 * survives checkpoint serialization; nodes rebuild it via fromArray().
 *
 * Mirrors the arguments the legacy OrchestratorService assembles before invoking
 * PlannerService / ExecutorService / AuditorService / ... — see those services'
 * method signatures.
 */
final class PipelineContext
{
    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $routerContext
     * @param  array<string, mixed>  $memoryContext
     * @param  array<string, mixed>  $skillRow
     * @param  list<string>  $ruleLines
     * @param  array<int, mixed>  $preflightReads
     * @param  array<string, mixed>  $specialistContext
     * @param  array<int, mixed>  $conversation
     */
    public function __construct(
        public readonly string $prompt,
        public readonly string $workflow = 'orchestrator_executor',
        public readonly array $modelRoute = [],
        public readonly array $routerContext = [],
        public readonly array $memoryContext = [],
        public readonly array $skillRow = [],
        public readonly array $ruleLines = [],
        public readonly string $playbookExcerpt = '',
        public readonly string $checklistExcerpt = '',
        public readonly ?string $allowedTool = null,
        public readonly string $executorProfileKey = 'default',
        public readonly array $preflightReads = [],
        public readonly string $workspaceContext = '',
        public readonly bool $highRiskContext = false,
        public readonly array $specialistContext = [],
        public readonly array $conversation = [],
        public readonly ?string $runId = null,
    ) {}

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            prompt: (string) ($a['prompt'] ?? ''),
            workflow: (string) ($a['workflow'] ?? 'orchestrator_executor'),
            modelRoute: (array) ($a['model_route'] ?? []),
            routerContext: (array) ($a['router_context'] ?? []),
            memoryContext: (array) ($a['memory_context'] ?? []),
            skillRow: (array) ($a['skill_row'] ?? []),
            ruleLines: array_values((array) ($a['rule_lines'] ?? [])),
            playbookExcerpt: (string) ($a['playbook_excerpt'] ?? ''),
            checklistExcerpt: (string) ($a['checklist_excerpt'] ?? ''),
            allowedTool: $a['allowed_tool'] ?? null,
            executorProfileKey: (string) ($a['executor_profile_key'] ?? 'default'),
            preflightReads: (array) ($a['preflight_reads'] ?? []),
            workspaceContext: (string) ($a['workspace_context'] ?? ''),
            highRiskContext: (bool) ($a['high_risk_context'] ?? false),
            specialistContext: (array) ($a['specialist_context'] ?? []),
            conversation: (array) ($a['conversation'] ?? []),
            runId: $a['run_id'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'workflow' => $this->workflow,
            'model_route' => $this->modelRoute,
            'router_context' => $this->routerContext,
            'memory_context' => $this->memoryContext,
            'skill_row' => $this->skillRow,
            'rule_lines' => $this->ruleLines,
            'playbook_excerpt' => $this->playbookExcerpt,
            'checklist_excerpt' => $this->checklistExcerpt,
            'allowed_tool' => $this->allowedTool,
            'executor_profile_key' => $this->executorProfileKey,
            'preflight_reads' => $this->preflightReads,
            'workspace_context' => $this->workspaceContext,
            'high_risk_context' => $this->highRiskContext,
            'specialist_context' => $this->specialistContext,
            'conversation' => $this->conversation,
            'run_id' => $this->runId,
        ];
    }

    public function withRunId(string $runId): self
    {
        $data = $this->toArray();
        $data['run_id'] = $runId;

        return self::fromArray($data);
    }
}
