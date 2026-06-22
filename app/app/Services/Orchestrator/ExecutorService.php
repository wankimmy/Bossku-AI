<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use App\Support\LlmTelemetry;
use App\Support\StringCoercion;

class ExecutorService
{
    use AgentConversationTrait;

    /** Task types where a successful run is expected to change files or run commands. */
    private const READ_ONLY_TASK_TYPES = ['question', 'unknown', ''];

    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected AgentPersonaService $personas,
        protected ?RuntimeSettings $settings = null,
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
        ?array $auditFeedback = null,
        array $memoryContext = [],
        array $conversation = [],
        ?string $runId = null,
        array $specialistContext = [],
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

        $memBlock = $this->buildMemoryBlock($memoryContext);
        $specialistBlock = $this->buildSpecialistBlock($specialistContext);
        $conversationBlock = $this->buildConversationBlock($conversation);
        $plannerQuestions = is_array($plan['planner_questions'] ?? null) && $plan['planner_questions'] !== []
            ? "\nUNRESOLVED PLANNER QUESTIONS (address these if relevant to your work):\n".implode("\n", array_map(
                fn ($q) => '- '.(is_array($q) ? ($q['question'] ?? json_encode($q)) : (string) $q),
                $plan['planner_questions']
            ))
            : '';
        $designSpec = is_array($plan['design_spec'] ?? null) && $plan['design_spec'] !== []
            ? "\nDESIGN SPEC (from Designer — follow tokens, layout, states, and file scope):\n".$this->jsonEncode($plan['design_spec'])
            : '';
        $planConfidence = is_numeric($plan['confidence'] ?? null) ? (float) $plan['confidence'] : null;
        if ($planConfidence !== null && $planConfidence < 0.50) {
            $confidenceNote = "\nCRITICAL WARNING: Planner confidence is very low ({$planConfidence}). The plan may be incomplete or based on incorrect assumptions. Read every target file before making any changes. If you cannot find the files described in the plan, STOP and report back rather than guessing.";
        } elseif ($planConfidence !== null && $planConfidence < 0.65) {
            $confidenceNote = "\nWARNING: Planner confidence is low ({$planConfidence}). Be extra careful — read the files before editing them.";
        } else {
            $confidenceNote = '';
        }

        $payload = <<<Markdown
Executor metadata:
Skill: {$skillName}
Planner confidence: {$planConfidence}

Conversation history (most recent turns):
{$conversationBlock}

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
{$confidenceNote}
{$plannerQuestions}
{$designSpec}
Prior memory context (lessons from past runs — you MUST apply these):
{$memBlock}

Specialist agent handoff (apply this if present):
{$specialistBlock}

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
You are the BosskuAI Executor — Stage 2 of 3 in a three-stage pipeline (Planner → Executor → Auditor).

PIPELINE CONTEXT:
- The Planner (Stage 1) has already analysed the task and produced a concrete plan with a checklist. Your job is to implement it exactly.
- The Auditor (Stage 3) will adversarially verify your output against the plan. It will reject work that: invents file paths, claims changes without evidence, ignores prior memory lessons, or leaves checklist items unaddressed.
- You have access to conversation history — use it to understand prior attempts, failures, and user intent.

WHAT YOU MUST DO:
1. READ the conversation history — if the user said "retry" or referenced a prior attempt, understand what was tried and what failed.
2. READ memory context — identify which [Memory N] lessons apply to this task and explicitly apply them.
3. IMPLEMENT the plan checklist items exactly — update each item's status in checklist_status.
4. CITE evidence in handoff_message — "Files read: [path], Files changed: [path] ([summary]), Commands run: [cmd]".
5. If you have questions for the user BEFORE proceeding with a destructive or irreversible action, surface them in executor_questions.

RUNTIME TOOLS (critical):
- "Allowed tools" in your persona block are BosskuAI runtime capabilities (file_read_safe, file_search, file_glob, file_write_proposed, etc.), NOT external editor tools.
- BosskuAI applies your output automatically: return `files_changed` with `after` or `diff`, and `commands_run` for shell work. Do NOT ask the user to enable file_read, file_write, or shell access.
- For scaffolding in an empty project (no preflight hits), create new files by returning full `after` contents for each path in `files_changed`.
- Never block on missing tool permissions — if you cannot implement, set status to "partial" or "failed" with blockers, not open_questions about enabling tools.

HONESTY RULES (hard constraints):
- NEVER invent file paths. Only work on files in target_file_list or preflight reads.
- Do NOT claim a file was changed unless you provide the complete `after` contents or a valid `diff`.
- Only mark a checklist_status item completed when you can cite concrete evidence for it: changed file contents/diff, successful command output, test result, or proof file.
- If a required local command blocks progress, set status to "partial", set needs_user_input true, add blockers, and mark affected checklist_status rows "partial" instead of "completed".
- Do NOT set needs_user_input for routine partial work — only for hard blockers, permission errors, destructive actions, or genuinely ambiguous targets.
- If a prior memory lesson says "X failed before", explain in patch_summary what you did differently this time.

Output JSON only (no markdown fences). Required keys:
status ("success"|"partial"|"failed"),
files_read (array of {path, reason}),
files_changed (array of {path, change_type, summary, why, edits?, after?, diff?}),
commands_run (array of {command: string, cwd?: string} — cwd optional for sibling repos under /workspace; omit to use active project),
tests_run, tests_result, patch_summary,
known_issues, needs_user_input, blockers, suggested_options, needs_audit,
handoff_message (MUST cite: files read, files changed with paths, commands run),
executor_questions (array of {id: string, question: string, why: string} — questions for the user before proceeding with high-stakes actions; empty if no blockers),
memory_lessons_applied (string[] — cite which [Memory N] lessons you applied and how; empty if none relevant),
checklist_status (array of {id: string, status: "completed"|"partial"|"failed"|"skipped", notes: string} — one entry per plan checklist item).

File write rules:
- READS ARE LINE-NUMBERED: file_read_safe and preflight reads show each line as `<n>: <content>`. When you quote text into `old_string`/diffs, copy ONLY the content after the `<n>: ` prefix — never include the line number. Large files are paged; request more with file_read_safe {path, offset, limit}.
- For MODIFY operations on existing files: PREFER surgical `edits` — an array of {old_string, new_string, replace_all?}. `old_string` is the EXACT snippet to replace (quoted verbatim from the file you read, with enough surrounding lines to be unique); `new_string` is its replacement. Set replace_all=true only to rename every occurrence. This is the most reliable and token-cheap mechanism: the system locates the snippet even if whitespace/indentation drift, and rejects edits that are not found or ambiguous so you can retry with a longer, more specific `old_string`.
  - Keep each `old_string` minimal but UNIQUE. If an edit comes back "ambiguous", add more surrounding context. If "not found", re-read the file and quote the real text.
  - Use multiple entries in `edits` for several changes to the same file.
- Use a unified `diff` only if you cannot express the change as `edits`. Format: `--- path\n+++ path\n @@ ... @@\n -old\n +new\n context`.
- For CREATE operations (new file): return the complete file contents in `after`.
- For DELETE operations: set change_type to "deleted" and omit `edits`, `after`, and `diff`.
- NEVER put placeholders, "...", or "rest of file unchanged" in `edits`, `after`, or `diff`.
- For a whole-file rewrite, set `after` to the complete file. Never mix `edits` and `after` on the same file.
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

        $strict = $this->settings?->executorStrictValidation() ?? false;
        $expectsChanges = $strict && $this->planExpectsFileChanges($plan, $modelRoute);

        $t0 = microtime(true);
        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                (float) ($profile['temperature'] ?? 0.2),
                $retry,
                'executor',
                fn (mixed $j): bool => is_array($j) && ExecutorResponseParser::validateForFallback($j, $strict, $expectsChanges),
                (int) ($profile['max_tokens'] ?? 8192),
                $runId,
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
                'executor_questions' => [],
                'memory_lessons_applied' => [],
                'checklist_status' => [],
                '_executor_model' => $primary,
                'latency_ms' => (int) round((microtime(true) - $t0) * 1000),
            ], $preflightReads);
        }

        $latency = (int) round((microtime(true) - $t0) * 1000);
        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? ExecutorResponseParser::normalize($out['parsed']) : [];

        return LlmTelemetry::mergeAgentResult($this->normalizeResult([
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
            'executor_questions' => is_array($parsed['executor_questions'] ?? null) ? $parsed['executor_questions'] : [],
            'memory_lessons_applied' => is_array($parsed['memory_lessons_applied'] ?? null) ? $parsed['memory_lessons_applied'] : [],
            'checklist_status' => is_array($parsed['checklist_status'] ?? null) ? $parsed['checklist_status'] : [],
            '_executor_model' => $out['model_used'],
            '_executor_model_resolved' => $out['model_resolved'] ?? '',
            '_executor_fallback' => $out['fallback_used'],
            'latency_ms' => $latency,
        ], $preflightReads), $out);
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
                'edits' => $this->normalizeEdits($item['edits'] ?? null),
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

            $row = [
                'command' => StringCoercion::toString($item['command'] ?? null),
                'status' => StringCoercion::toString($item['status'] ?? null, 'completed'),
                'exit_code' => $item['exit_code'] ?? null,
                'duration_ms' => $item['duration_ms'] ?? null,
                'output_summary' => StringCoercion::toString($item['output_summary'] ?? null),
            ];
            $cwd = StringCoercion::toString($item['cwd'] ?? $item['working_directory'] ?? null, '');
            if ($cwd !== '') {
                $row['cwd'] = $cwd;
            }

            return $row;
        }, is_array($result['commands_run'] ?? null) ? $result['commands_run'] : []), fn ($item) => $item !== null && $item['command'] !== ''));

        $result['tests_run'] = is_array($result['tests_run'] ?? null) ? $result['tests_run'] : [];
        if ($result['tests_run'] === [] && ($result['tests_result'] ?? 'not_run') !== 'not_run') {
            $result['tests_run'] = [[
                'name' => 'Executor reported tests',
                'status' => StringCoercion::toString($result['tests_result'] ?? null, 'not_run'),
                'summary' => 'Executor returned tests_result='.StringCoercion::toString($result['tests_result'] ?? null, 'not_run'),
            ]];
        }

        $result['executor_questions'] = is_array($result['executor_questions'] ?? null) ? $result['executor_questions'] : [];
        $result['memory_lessons_applied'] = is_array($result['memory_lessons_applied'] ?? null) ? $result['memory_lessons_applied'] : [];
        $result['checklist_status'] = is_array($result['checklist_status'] ?? null) ? $result['checklist_status'] : [];

        if ($preflightReads !== [] && ExecutorEvidenceSupport::countFilesRead($result) === 0) {
            return ExecutorEvidenceSupport::mergePreflightReads($result, $preflightReads);
        }

        return $result;
    }

    /**
     * Write intent: the plan targets concrete files and the route classified the
     * task as something other than a read-only question. Used by the strict
     * validation gate to reject "success" responses that touched nothing.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     */
    protected function planExpectsFileChanges(array $plan, array $modelRoute): bool
    {
        $targets = is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [];
        if ($targets === []) {
            return false;
        }

        $taskType = strtolower(StringCoercion::toString($modelRoute['task_type'] ?? null, ''));

        return ! in_array($taskType, self::READ_ONLY_TASK_TYPES, true);
    }

    /**
     * Normalize the surgical `edits` array on a files_changed item to a clean
     * list of {old_string, new_string, replace_all}. Returns null when absent
     * or empty so the applier falls through to `after`/`diff`.
     *
     * @return list<array{old_string: string, new_string: string, replace_all: bool}>|null
     */
    protected function normalizeEdits(mixed $edits): ?array
    {
        if (! is_array($edits) || $edits === []) {
            return null;
        }

        $out = [];
        foreach ($edits as $edit) {
            if (! is_array($edit)) {
                continue;
            }
            $old = StringCoercion::toString($edit['old_string'] ?? $edit['oldString'] ?? null, '');
            if ($old === '') {
                continue;
            }
            $out[] = [
                'old_string' => $old,
                'new_string' => StringCoercion::toString($edit['new_string'] ?? $edit['newString'] ?? null, ''),
                'replace_all' => (bool) ($edit['replace_all'] ?? $edit['replaceAll'] ?? false),
            ];
        }

        return $out === [] ? null : $out;
    }

    /** @param list<array<string,mixed>> $memories */
    protected function buildMemoryBlock(array $memories): string
    {
        if ($memories === []) {
            return '(no prior memory retrieved)';
        }
        $lines = [];
        foreach ($memories as $i => $m) {
            $summary = is_array($m) ? ($m['summary'] ?? $m['content'] ?? '') : (string) $m;
            $lines[] = '[Memory '.($i + 1).'] '.$summary;
        }

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $specialistContext */
    protected function buildSpecialistBlock(array $specialistContext): string
    {
        if ($specialistContext === []) {
            return '(no specialist agent selected for this run)';
        }

        return $this->jsonEncode($specialistContext);
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
