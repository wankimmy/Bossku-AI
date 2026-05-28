<?php

namespace App\Services\Specialists;

use App\Models\BosskuAi\SpecialistAgent;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Support\LlmTelemetry;
use App\Support\StringCoercion;

class SpecialistAgentRunner
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $routerContext
     * @param  list<array<string, mixed>>  $memoryContext
     * @return array<string, mixed>
     */
    public function run(
        SpecialistAgent $agent,
        string $userPrompt,
        string $projectContext,
        array $plan,
        array $routerContext,
        array $memoryContext,
        ?string $linkedSkillContent = null,
        ?string $runId = null,
    ): array {
        $cfg = $this->modelConfig->orchestrator();
        $primary = (string) ($cfg['primary'] ?? '');
        $fallbacks = is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : [];
        $models = array_values(array_filter(array_unique(array_merge([$primary], $fallbacks))));
        if ($models === []) {
            $models = ['kimi-k2.6'];
        }

        $system = <<<SYS
You are {$agent->display_name}, a project-scoped BosskuAI specialist agent.

Specialist persona:
{$agent->persona_content}

Return ONLY valid JSON with these keys:
summary (string),
task_strategy (string[]),
pitfalls (string[]),
files_or_areas_to_focus (string[]),
handoff_to_executor (string).
SYS;

        $payload = [
            'user_prompt' => $userPrompt,
            'active_project_context' => $projectContext,
            'planner_plan' => $plan,
            'skill_router_context' => $routerContext,
            'memory_context' => $memoryContext,
            'linked_skill_content' => $linkedSkillContent,
            'trigger_keywords' => $agent->trigger_keywords ?? [],
        ];

        $t0 = microtime(true);
        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode($payload, JSON_PRETTY_PRINT) ?: '{}'],
                ],
                (float) ($cfg['temperature'] ?? 0.2),
                (int) ($cfg['retry_count'] ?? 0),
                'specialist_agent',
                fn (mixed $j): bool => $this->validOutput($j),
                (int) ($cfg['max_tokens'] ?? 4096),
                $runId,
                null,
                [
                    'specialist_agent_id' => $agent->id,
                    'specialist_role_slug' => $agent->role_slug,
                ],
            );
            $latency = (int) round((microtime(true) - $t0) * 1000);

            /** @var array<string, mixed> $parsed */
            $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

            return LlmTelemetry::mergeAgentResult($this->normalize($parsed, [
                '_specialist_model' => $out['model_used'],
                '_specialist_model_resolved' => $out['model_resolved'] ?? '',
                '_specialist_provider' => $out['provider_used'] ?? '',
                '_specialist_fallback' => $out['fallback_used'] ?? false,
                'latency_ms' => $latency,
            ]), $out);
        } catch (\Throwable $e) {
            return $this->normalize([
                'summary' => $agent->display_name.' could not complete an LLM handoff.',
                'task_strategy' => ['Use the planner checklist and apply the specialist persona manually.'],
                'pitfalls' => [$e->getMessage()],
                'files_or_areas_to_focus' => [],
                'handoff_to_executor' => 'Specialist LLM step failed; proceed with planner output and linked skill context.',
                '_specialist_model' => $models[0] ?? '',
                '_specialist_error' => $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);
        }
    }

    protected function validOutput(mixed $output): bool
    {
        if (! is_array($output)) {
            return false;
        }

        return isset($output['summary'], $output['task_strategy'], $output['pitfalls'], $output['files_or_areas_to_focus'], $output['handoff_to_executor'])
            && is_array($output['task_strategy'])
            && is_array($output['pitfalls'])
            && is_array($output['files_or_areas_to_focus']);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function normalize(array $result, array $extra = []): array
    {
        return array_merge([
            'summary' => StringCoercion::toString($result['summary'] ?? null),
            'task_strategy' => $this->stringList($result['task_strategy'] ?? []),
            'pitfalls' => $this->stringList($result['pitfalls'] ?? []),
            'files_or_areas_to_focus' => $this->stringList($result['files_or_areas_to_focus'] ?? []),
            'handoff_to_executor' => StringCoercion::toString($result['handoff_to_executor'] ?? null),
        ], $result, $extra);
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => StringCoercion::toString($item),
            $value,
        )));
    }
}
