<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class ExecutorService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig
    ) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $skillRow
     * @param  list<string>  $ruleLines
     * @param  array<string, mixed>  $plan  narrowed orchestrator plan
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    public function execute(
        array $step,
        array $skillRow,
        array $ruleLines,
        string $playbookExcerpt,
        string $checklistExcerpt,
        ?string $allowedTool,
        array $plan,
        array $modelRoute,
        string $executorProfileKey
    ): array {
        $skillName = (string) ($step['skill'] ?? $skillRow['name'] ?? 'unknown');
        $task = (string) ($step['task'] ?? '');
        $profile = $this->modelConfig->executorProfile($executorProfileKey);
        $primary = (string) ($profile['primary'] ?? 'kimi-k2.6');
        $fallbacks = is_array($profile['fallback'] ?? null) ? $profile['fallback'] : [];
        $models = array_values(array_unique(array_merge([$primary], $fallbacks)));
        $retry = (int) ($profile['retry_count'] ?? 1);
        $rulesBlock = implode("\n", array_map(fn ($r) => '- '.$r, $ruleLines));
        $execRules = implode("\n", array_map(fn ($r) => '- '.$r, $this->modelConfig->executorRules()));

        $payload = <<<Markdown
[BOSSKUAI]
Skill: {$skillName}

Orchestrator plan (JSON):
{$this->jsonEncode($plan)}

Executor rules:
{$execRules}

Routing context:
{$this->jsonEncode($modelRoute)}

Rules:
{$rulesBlock}

Playbook:
{$playbookExcerpt}

Checklist:
{$checklistExcerpt}

Task:
{$task}

Allowed tool:
{$allowedTool}

You may only read/edit files listed in target_file_list unless allow_broad_repo_scan is true.

Output format (JSON only, no markdown):
{
  "status": "success|partial|failed",
  "files_changed": [],
  "commands_run": [],
  "tests_result": "passed|failed|not_run",
  "patch_summary": "",
  "known_issues": [],
  "needs_audit": true
}
Markdown;

        $messages = [
            ['role' => 'system', 'content' => 'You are the BosskuAI executor. Follow the skill and rules. Output JSON only.'],
            ['role' => 'user', 'content' => $payload],
        ];

        $t0 = microtime(true);
        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                (float) ($profile['temperature'] ?? 0.2),
                $retry,
                'executor',
                function (mixed $j): bool {
                    return is_array($j) && isset($j['status'], $j['patch_summary']);
                },
                (int) ($profile['max_tokens'] ?? 12000)
            );
        } catch (\Throwable $e) {
            return [
                'step_id' => $step['id'] ?? null,
                'status' => 'failed',
                'files_changed' => [],
                'commands_run' => [],
                'tests_result' => 'not_run',
                'patch_summary' => '',
                'known_issues' => [$e->getMessage()],
                'needs_audit' => true,
                '_executor_model' => $primary,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            ];
        }

        $latency = (int) round((microtime(true) - $t0) * 1000);
        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return [
            'step_id' => $step['id'] ?? null,
            'status' => (string) ($parsed['status'] ?? 'success'),
            'files_changed' => is_array($parsed['files_changed'] ?? null) ? $parsed['files_changed'] : [],
            'commands_run' => is_array($parsed['commands_run'] ?? null) ? $parsed['commands_run'] : [],
            'tests_result' => (string) ($parsed['tests_result'] ?? 'not_run'),
            'patch_summary' => (string) ($parsed['patch_summary'] ?? ''),
            'known_issues' => is_array($parsed['known_issues'] ?? null) ? $parsed['known_issues'] : [],
            'needs_audit' => (bool) ($parsed['needs_audit'] ?? true),
            '_executor_model' => $out['model_used'],
            '_executor_fallback' => $out['fallback_used'],
            'latency_ms' => $latency,
        ];
    }

    /** @param array<string, mixed> $data */
    protected function jsonEncode(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\Throwable) {
            return '{}';
        }
    }
}
