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
        string $executorProfileKey,
        string $workspaceContext = '',
        array $preflightReads = [],
        ?array $auditFeedback = null
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

Audit feedback for revision:
{$this->jsonEncode($auditFeedback ?? [])}

Workspace (mandatory — use relative paths only):
{$workspaceContext}

Preflight file_read_safe results (real repository reads; cite these paths only):
{$this->jsonEncode($preflightReads)}

Task:
{$task}

Allowed tool:
{$allowedTool}

You may only read/edit files listed in target_file_list unless allow_broad_repo_scan is true.

Output format (JSON only, no markdown):
{
  "status": "success|partial|failed",
  "files_read": [{"path": "relative/path", "reason": "why this file was inspected"}],
  "files_changed": [{"path": "relative/path", "change_type": "created|modified|deleted|renamed", "summary": "what changed", "why": "why it changed", "diff": "unified diff when available"}],
  "commands_run": [{"command": "command", "status": "passed|failed|skipped", "exit_code": 0, "duration_ms": 0, "output_summary": "short summary"}],
  "tests_run": [{"name": "test or suite name", "status": "passed|failed|skipped", "summary": "short summary"}],
  "tests_result": "passed|failed|not_run",
  "patch_summary": "",
  "known_issues": [],
  "needs_user_input": false,
  "blockers": [],
  "suggested_options": [{"id": "narrow", "label": "Retry with a narrower scope"}],
  "needs_audit": true,
  "handoff_message": "Sending changes to Auditor"
}

If you cannot proceed without a user decision, set needs_user_input to true, list blockers, and provide 2-4 suggested_options the user can pick.
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
            return $this->normalizeResult([
                'step_id' => $step['id'] ?? null,
                'status' => 'failed',
                'files_read' => [],
                'files_changed' => [],
                'commands_run' => [],
                'tests_run' => [],
                'tests_result' => 'not_run',
                'patch_summary' => '',
                'known_issues' => [$e->getMessage()],
                'needs_audit' => true,
                '_executor_model' => $primary,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            ], $preflightReads);
        }

        $latency = (int) round((microtime(true) - $t0) * 1000);
        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return $this->normalizeResult([
            'step_id' => $step['id'] ?? null,
            'status' => (string) ($parsed['status'] ?? 'success'),
            'files_read' => is_array($parsed['files_read'] ?? null) ? $parsed['files_read'] : [],
            'files_changed' => is_array($parsed['files_changed'] ?? null) ? $parsed['files_changed'] : [],
            'commands_run' => is_array($parsed['commands_run'] ?? null) ? $parsed['commands_run'] : [],
            'tests_run' => is_array($parsed['tests_run'] ?? null) ? $parsed['tests_run'] : [],
            'tests_result' => (string) ($parsed['tests_result'] ?? 'not_run'),
            'patch_summary' => (string) ($parsed['patch_summary'] ?? ''),
            'known_issues' => is_array($parsed['known_issues'] ?? null) ? $parsed['known_issues'] : [],
            'needs_user_input' => (bool) ($parsed['needs_user_input'] ?? false),
            'blockers' => is_array($parsed['blockers'] ?? null) ? $parsed['blockers'] : [],
            'suggested_options' => is_array($parsed['suggested_options'] ?? null) ? $parsed['suggested_options'] : [],
            'needs_audit' => (bool) ($parsed['needs_audit'] ?? true),
            'handoff_message' => (string) ($parsed['handoff_message'] ?? 'Sending changes to Auditor.'),
            '_executor_model' => $out['model_used'],
            '_executor_model_resolved' => $out['model_resolved'] ?? '',
            '_executor_fallback' => $out['fallback_used'],
            'latency_ms' => $latency,
        ], $preflightReads);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $preflightReads
     */
    protected function normalizeResult(array $result, array $preflightReads = []): array
    {
        $result['files_read'] = array_values(array_filter(array_map(function ($item) {
            if (! is_array($item)) {
                return null;
            }

            return [
                'path' => (string) ($item['path'] ?? ''),
                'reason' => (string) ($item['reason'] ?? ''),
            ];
        }, is_array($result['files_read'] ?? null) ? $result['files_read'] : []), fn ($item) => $item !== null && $item['path'] !== ''));

        $result['files_changed'] = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return ['path' => $item, 'change_type' => 'modified', 'summary' => '', 'why' => '', 'diff' => null];
            }
            if (! is_array($item)) {
                return null;
            }

            return [
                'path' => (string) ($item['path'] ?? ''),
                'change_type' => (string) ($item['change_type'] ?? 'modified'),
                'summary' => (string) ($item['summary'] ?? $item['description'] ?? ''),
                'why' => (string) ($item['why'] ?? ''),
                'diff' => $item['diff'] ?? null,
            ];
        }, is_array($result['files_changed'] ?? null) ? $result['files_changed'] : []), fn ($item) => $item !== null && $item['path'] !== ''));

        $result['commands_run'] = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return ['command' => $item, 'status' => 'completed'];
            }
            if (! is_array($item)) {
                return null;
            }

            return [
                'command' => (string) ($item['command'] ?? ''),
                'status' => (string) ($item['status'] ?? 'completed'),
                'exit_code' => $item['exit_code'] ?? null,
                'duration_ms' => $item['duration_ms'] ?? null,
                'output_summary' => (string) ($item['output_summary'] ?? ''),
            ];
        }, is_array($result['commands_run'] ?? null) ? $result['commands_run'] : []), fn ($item) => $item !== null && $item['command'] !== ''));

        $result['tests_run'] = is_array($result['tests_run'] ?? null) ? $result['tests_run'] : [];
        if ($result['tests_run'] === [] && ($result['tests_result'] ?? 'not_run') !== 'not_run') {
            $result['tests_run'] = [[
                'name' => 'Executor reported tests',
                'status' => (string) $result['tests_result'],
                'summary' => 'Executor returned tests_result='.$result['tests_result'],
            ]];
        }

        if ($preflightReads !== [] && ExecutorEvidenceSupport::countFilesRead($result) === 0) {
            return ExecutorEvidenceSupport::mergePreflightReads($result, $preflightReads);
        }

        return $result;
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
