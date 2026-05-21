<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\LlmErrorFormatter;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectService;
use Illuminate\Support\Arr;

class PlannerService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected RuntimeSettings $settings,
        protected ProjectService $projects,
        protected ProjectFileDiscovery $discovery,
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
        $primary = (string) ($orch['primary'] ?? $this->settings->orchestratorModelForRouting());
        $fallbacks = is_array($orch['fallback'] ?? null) ? $orch['fallback'] : [];
        $models = array_values(array_unique(array_merge([$primary], $fallbacks)));
        $retry = (int) ($orch['retry_count'] ?? 1);

        $system = <<<'SYS'
You are the BosskuAI orchestrator. Output ONLY valid JSON (no markdown) with keys:
task_summary (string),
goal (string),
risk_level ("low"|"medium"|"high"),
selected_skill (string),
memory_strategy ("none"|"read_only"|"read_and_write"),
expected_artifacts (string[]),
checklist (array of {id: string, title: string, description: string, owner: string, status: "pending"|"running"|"completed"|"needs_revision"|"failed"|"skipped"}),
summary (string, one line),
target_file_list (array of {path: string, reason: string}),
allow_broad_repo_scan (boolean),
executor_profile (one of: default, frontend_ui, backend, devops, high_risk),
suggested_tests (string[]),
risk_notes (string[]),
constraints (string[]),
handoff_message (string).
Do not invent file paths; if unknown, use empty target_file_list and set allow_broad_repo_scan true only when strictly necessary.
Use relative paths from the repository root only (e.g. app/Http/Controllers/FooController.php, config/database.php).
SYS;
        $system .= "\n\n".$this->projects->evidenceRuleForPrompt();

        $repoIndex = '';
        try {
            $repoIndex = $this->discovery->repoIndexForPlanner();
        } catch (\Throwable $e) {
            $repoIndex = 'Repo index unavailable: '.$e->getMessage();
        }

        $user = json_encode([
            'prompt' => $prompt,
            'memory' => $memoryContext,
            'skill_router' => Arr::except($routerContext, ['_scores']),
            'routing' => $modelRoute,
            'repo_index' => $repoIndex,
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
                  return is_array($j) && (isset($j['summary']) || isset($j['task_summary']));
              },
              (int) ($orch['max_tokens'] ?? 8192)
          );
          /** @var array<string, mixed> $decoded */
          $decoded = is_array($out['parsed']) ? $out['parsed'] : [];
          $decoded = $this->normalizePlan($decoded, $prompt, $routerContext, $modelRoute);
          $decoded['_planner_model'] = $out['model_used'];
          $decoded['_planner_model_resolved'] = $out['model_resolved'] ?? '';
          $decoded['_planner_fallback'] = $out['fallback_used'];

          return $decoded;
      } catch (\Throwable $e) {
          $message = LlmErrorFormatter::humanize($e->getMessage());

          return [
              'error' => true,
              'message' => $message,
          ];
      }
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $routerContext
     * @param array<string, mixed> $modelRoute
     * @return array<string, mixed>
     */
    protected function normalizePlan(array $decoded, string $prompt, array $routerContext, array $modelRoute): array
    {
        $skill = (string) ($decoded['selected_skill'] ?? $routerContext['primary_skill']['name'] ?? $modelRoute['skill'] ?? 'general');
        $checklist = $decoded['checklist'] ?? null;

        if (! is_array($checklist) || $checklist === []) {
            $checklist = [
                [
                    'id' => 'plan-1',
                    'title' => 'Inspect relevant files',
                    'description' => 'Read only files needed for this task.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-2',
                    'title' => 'Apply focused changes',
                    'description' => 'Modify only files related to the request.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-3',
                    'title' => 'Audit result',
                    'description' => 'Check correctness, security, performance, and maintainability.',
                    'owner' => 'auditor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-4',
                    'title' => 'Fix audit feedback',
                    'description' => 'Apply required fixes if auditor returns needs_revision.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-5',
                    'title' => 'Finalize response',
                    'description' => 'Summarize files changed, checks run, risks, and next step.',
                    'owner' => 'final-reviewer',
                    'status' => 'pending',
                ],
            ];
        }

        $decoded['task_summary'] = (string) ($decoded['task_summary'] ?? $decoded['summary'] ?? mb_substr($prompt, 0, 220));
        $decoded['goal'] = (string) ($decoded['goal'] ?? 'Complete the user request with focused changes and visible audit trail.');
        $decoded['risk_level'] = (string) ($decoded['risk_level'] ?? $modelRoute['risk_level'] ?? 'medium');
        $decoded['selected_skill'] = $skill;
        $decoded['memory_strategy'] = (string) ($decoded['memory_strategy'] ?? $modelRoute['memory_mode'] ?? 'read_only');
        $decoded['expected_artifacts'] = is_array($decoded['expected_artifacts'] ?? null)
            ? $decoded['expected_artifacts']
            : ['files_changed', 'audit_findings', 'final_summary'];
        $decoded['checklist'] = array_values(array_map(
            fn ($item, $idx) => [
                'id' => (string) ($item['id'] ?? 'plan-'.($idx + 1)),
                'title' => (string) ($item['title'] ?? 'Plan item '.($idx + 1)),
                'description' => (string) ($item['description'] ?? ''),
                'owner' => (string) ($item['owner'] ?? 'executor'),
                'status' => (string) ($item['status'] ?? 'pending'),
            ],
            $checklist,
            array_keys($checklist)
        ));
        $decoded['summary'] = (string) ($decoded['summary'] ?? $decoded['task_summary']);
        $decoded['target_file_list'] = is_array($decoded['target_file_list'] ?? null) ? $decoded['target_file_list'] : [];
        $decoded['allow_broad_repo_scan'] = (bool) ($decoded['allow_broad_repo_scan'] ?? ($decoded['target_file_list'] === []));
        $decoded['executor_profile'] = (string) ($decoded['executor_profile'] ?? $modelRoute['executor_profile'] ?? 'default');
        $decoded['suggested_tests'] = is_array($decoded['suggested_tests'] ?? null) ? $decoded['suggested_tests'] : [];
        $decoded['risk_notes'] = is_array($decoded['risk_notes'] ?? null) ? $decoded['risk_notes'] : [];
        $decoded['constraints'] = is_array($decoded['constraints'] ?? null) ? $decoded['constraints'] : [];
        $decoded['handoff_message'] = (string) ($decoded['handoff_message'] ?? 'Sending execution task to Executor.');

        return $decoded;
    }
}
