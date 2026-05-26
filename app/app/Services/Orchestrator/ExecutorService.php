<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Support\StringCoercion;

class ExecutorService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected AgentPersonaService $personas
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

Preflight file_read_safe results:
{$this->jsonEncode(
    ExecutorEvidenceSupport::wantsPreviewReadsInExecutorPrompt($modelRoute, $plan)
        ? ExecutorEvidenceSupport::readsWithPreviewForExecutorPrompt($preflightReads)
        : ExecutorEvidenceSupport::slimReadsForExecutorPrompt($preflightReads),
)}

Task:
{$task}

Allowed tool:
{$allowedTool}

You may only read/edit files listed in target_file_list unless allow_broad_repo_scan is true.
Markdown;

        $system = <<<'SYS'
You are the BosskuAI executor. Follow the skill and rules. Output JSON only (no markdown fences).

Required JSON keys:
status ("success"|"partial"|"failed"),
files_read (array of {path, reason}),
files_changed (array of {path, change_type, summary, why, after?, diff?}),
commands_run, tests_run, tests_result, patch_summary, known_issues,
needs_user_input, blockers, suggested_options, needs_audit, handoff_message.

If you cannot proceed without a user decision (hard blocker or high-risk/destructive choice), set needs_user_input to true, list blockers, and provide 2-4 suggested_options.
handoff_message MUST cite proof: "Files read: ...", "Files changed: path (summary)", "Commands: ..." — use paths from evidence only.
Do not set needs_user_input for routine partial work; only blockers, permission errors, ambiguous targets, or destructive actions needing consent.

Git undo: put exact allowlisted lines in commands_run (e.g. "git restore path/to/file.php"), one command per entry.
Project commands (run automatically in the active project root from workspace context; do not put commands in files_changed): git status/diff/restore/checkout; docker compose / docker compose exec <service>; php artisan …; php vendor/bin/phpunit; composer test. Use the compose service name from runtime hints for this repo — not a name from another project. Put exact command strings in commands_run — do not invent test results; use tests_run only after commands actually run.
Each file change is shown to the user for approve/reject with an optional comment before it is written — list proposals in files_changed; do not claim files are restored or deleted in patch_summary until the user could approve them.
Use past tense in patch_summary only for work the user has already approved.

File write rules (critical):
- For modify/create, `after` MUST be the complete final file contents, OR provide a valid unified `diff` that applies cleanly to the current file.
- NEVER put placeholders in `after` (e.g. TBD, "will be determined", "read file first", "to be filled", "..." only).
- If you do not have the full file in context, read it via files_read first, or set needs_user_input — do NOT queue a file_write without real content.
- Approving a bad `after` overwrites the entire file on disk.
- When audit feedback includes rejected_approvals, revert each listed path (git restore or exact before snapshot). Do not re-apply rejected edits.
- When audit feedback revision_type is user_code_review, apply code_review_instructions and re-propose files for user approval.
SYS;

        $isRejectedRevert = is_array($auditFeedback)
            && (($auditFeedback['revision_type'] ?? '') === 'rejected_file_writes'
                || isset($auditFeedback['rejected_approvals']));
        $isCodeReview = is_array($auditFeedback)
            && ($auditFeedback['revision_type'] ?? '') === 'user_code_review';
        $fromRole = ($isRejectedRevert || $isCodeReview) ? 'user' : ($auditFeedback !== null ? 'auditor' : 'orchestrator');
        $handoffMessage = $isCodeReview
            ? 'User requested code review changes. Apply their instructions, then propose updated files for approval.'
            : ($isRejectedRevert
                ? 'User rejected proposed file changes. Revert each rejected path to its before snapshot before continuing.'
                : ($auditFeedback !== null
                    ? 'Revision required from auditor feedback.'
                    : StringCoercion::toString($plan['handoff_message'] ?? null, 'Sending execution task to Executor.')));
        $userContent = $this->personas->wrapHandoffUserContent('executor', $fromRole, $handoffMessage, $payload);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];

        $t0 = microtime(true);
        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                (float) ($profile['temperature'] ?? 0.2),
                $retry,
                'executor',
                fn (mixed $j): bool => is_array($j) && ExecutorResponseParser::validateForFallback($j),
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
        $parsed = is_array($out['parsed']) ? ExecutorResponseParser::normalize($out['parsed']) : [];

        return $this->normalizeResult([
            'step_id' => $step['id'] ?? null,
            'status' => StringCoercion::toString($parsed['status'] ?? null, 'success'),
            'files_read' => is_array($parsed['files_read'] ?? null) ? $parsed['files_read'] : [],
            'files_changed' => is_array($parsed['files_changed'] ?? null) ? $parsed['files_changed'] : [],
            'commands_run' => is_array($parsed['commands_run'] ?? null) ? $parsed['commands_run'] : [],
            'tests_run' => is_array($parsed['tests_run'] ?? null) ? $parsed['tests_run'] : [],
            'tests_result' => StringCoercion::toString($parsed['tests_result'] ?? null, 'not_run'),
            'patch_summary' => StringCoercion::toString($parsed['patch_summary'] ?? null, ''),
            'known_issues' => is_array($parsed['known_issues'] ?? null) ? $parsed['known_issues'] : [],
            'needs_user_input' => (bool) ($parsed['needs_user_input'] ?? false),
            'questions' => is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [],
            'blockers' => is_array($parsed['blockers'] ?? null) ? $parsed['blockers'] : [],
            'suggested_options' => is_array($parsed['suggested_options'] ?? null) ? $parsed['suggested_options'] : [],
            'needs_audit' => (bool) ($parsed['needs_audit'] ?? true),
            'handoff_message' => StringCoercion::toString($parsed['handoff_message'] ?? null, 'Sending changes to Auditor.'),
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
                'path' => StringCoercion::toString($item['path'] ?? null),
                'reason' => StringCoercion::toString($item['reason'] ?? null),
            ];
        }, is_array($result['files_read'] ?? null) ? $result['files_read'] : []), fn ($item) => $item !== null && $item['path'] !== ''));

        $result['files_changed'] = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return ['path' => $item, 'change_type' => 'modified', 'summary' => '', 'why' => '', 'diff' => null];
            }
            if (! is_array($item)) {
                return null;
            }

            $after = StringCoercion::toString(
                $item['after'] ?? $item['new_contents'] ?? $item['contents'] ?? null,
                '',
            );

            return [
                'path' => StringCoercion::toString($item['path'] ?? null),
                'change_type' => StringCoercion::toString($item['change_type'] ?? null, 'modified'),
                'summary' => StringCoercion::toString($item['summary'] ?? $item['description'] ?? null),
                'why' => StringCoercion::toString($item['why'] ?? null),
                'after' => $after !== '' ? $after : null,
                'diff' => is_string($item['diff'] ?? null) ? $item['diff'] : null,
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
                'command' => StringCoercion::toString($item['command'] ?? null),
                'status' => StringCoercion::toString($item['status'] ?? null, 'completed'),
                'exit_code' => $item['exit_code'] ?? null,
                'duration_ms' => $item['duration_ms'] ?? null,
                'output_summary' => StringCoercion::toString($item['output_summary'] ?? null),
            ];
        }, is_array($result['commands_run'] ?? null) ? $result['commands_run'] : []), fn ($item) => $item !== null && $item['command'] !== ''));

        $result['tests_run'] = is_array($result['tests_run'] ?? null) ? $result['tests_run'] : [];
        if ($result['tests_run'] === [] && ($result['tests_result'] ?? 'not_run') !== 'not_run') {
            $result['tests_run'] = [[
                'name' => 'Executor reported tests',
                'status' => StringCoercion::toString($result['tests_result'] ?? null, 'not_run'),
                'summary' => 'Executor returned tests_result='.StringCoercion::toString($result['tests_result'] ?? null, 'not_run'),
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
