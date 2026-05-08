<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Support\Arr;

class PlannerService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected RuntimeSettings $settings
    ) {}

    /**
     * Orchestrator plan: scoped context + optional legacy steps.
     *
     * @param  array<int, mixed>  $memoryContext
     * @param  array<string, mixed>  $routerContext
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    public function plan(string $prompt, array $memoryContext, array $routerContext, array $modelRoute): array
    {
        $orch = $this->modelConfig->orchestrator();
        $override = $this->settings->orchestratorModelOverride();
        $primary = $override ?? (string) ($orch['primary'] ?? $this->settings->plannerModel());
        $fallbacks = is_array($orch['fallback'] ?? null) ? $orch['fallback'] : [];
        $models = array_values(array_unique(array_merge([$primary], $fallbacks)));
        $retry = (int) ($orch['retry_count'] ?? 1);

        $system = <<<'SYS'
You are the BosskuAI orchestrator. Output ONLY valid JSON (no markdown) with keys:
summary (string, one line),
target_file_list (array of {path: string, reason: string}),
allow_broad_repo_scan (boolean),
executor_profile (one of: default, frontend_ui, backend, devops, high_risk),
suggested_tests (string[]),
risk_notes (string[]),
constraints (string[]).
Do not invent file paths; if unknown, use empty target_file_list and set allow_broad_repo_scan true only when strictly necessary.
SYS;

        $user = json_encode([
            'prompt' => $prompt,
            'memory' => $memoryContext,
            'skill_router' => Arr::except($routerContext, ['_scores']),
            'routing' => $modelRoute,
        ], JSON_THROW_ON_ERROR);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

      try {
          $out = $this->fallback->chatWithFallbacks(
              $models,
              $messages,
              (float) ($orch['temperature'] ?? 0.2),
              $retry,
              'orchestrator',
              function (mixed $j): bool {
                  return is_array($j) && isset($j['summary'], $j['target_file_list']);
              },
              (int) ($orch['max_tokens'] ?? 8192)
          );
          /** @var array<string, mixed> $decoded */
          $decoded = is_array($out['parsed']) ? $out['parsed'] : [];
          $decoded['_planner_model'] = $out['model_used'];
          $decoded['_planner_model_resolved'] = $out['model_resolved'] ?? '';
          $decoded['_planner_fallback'] = $out['fallback_used'];

          return $decoded;
      } catch (\Throwable $e) {
          return [
              'error' => true,
              'message' => $e->getMessage(),
          ];
      }
    }
}
