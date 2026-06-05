<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\ToolCall;
use App\Support\StringCoercion;
use Illuminate\Support\Str;

/**
 * Shared helpers for executor file-read evidence (auditor / security auditor).
 */
class ExecutorEvidenceSupport
{
    /**
     * @param  array<string, mixed>  $execResult
     * @param  list<array<string, mixed>>  $preflightReads
     * @return array<string, mixed>
     */
    public static function mergePreflightReads(array $execResult, array $preflightReads): array
    {
        if ($preflightReads === []) {
            return $execResult;
        }

        $existing = is_array($execResult['files_read'] ?? null) ? $execResult['files_read'] : [];
        $paths = [];
        $merged = [];

        foreach (array_merge($preflightReads, $existing) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = (string) ($item['path'] ?? '');
            if ($path === '' || isset($paths[$path])) {
                continue;
            }
            $paths[$path] = true;
            $merged[] = [
                'path' => $path,
                'reason' => (string) ($item['reason'] ?? 'read during preflight'),
                'found' => $item['found'] ?? true,
            ];
        }

        $execResult['files_read'] = $merged;

        return $execResult;
    }

    /**
     * @param  array<string, mixed>  $execResult
     */
    public static function countFilesRead(array $execResult): int
    {
        $reads = is_array($execResult['files_read'] ?? null) ? $execResult['files_read'] : [];

        return count(array_filter($reads, static function ($item) {
            if (! is_array($item)) {
                return false;
            }
            if ((string) ($item['path'] ?? '') === '') {
                return false;
            }

            return ($item['found'] ?? true) === true;
        }));
    }

    /**
     * @param  array<string, mixed>  $execResult
     */
    public static function countFilesReadFailed(array $execResult): int
    {
        $reads = is_array($execResult['files_read'] ?? null) ? $execResult['files_read'] : [];

        return count(array_filter($reads, static function ($item) {
            return is_array($item)
                && (string) ($item['path'] ?? '') !== ''
                && ($item['found'] ?? true) !== true;
        }));
    }

    /**
     * @return list<array{tool: string, path: string, found: bool}>
     */
    public static function toolEvidenceForRun(?string $runId): array
    {
        if ($runId === null || $runId === '') {
            return [];
        }
        if (! Str::isUuid($runId)) {
            return [];
        }

        $rows = ToolCall::query()
            ->where('run_id', $runId)
            ->whereIn('tool', ['file_read_safe', 'file_search', 'file_glob'])
            ->where('status', 'ok')
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $payload = is_array($row->payload) ? $row->payload : [];
            $result = is_array($row->result) ? $row->result : [];
            if ($row->tool === 'file_search' || $row->tool === 'file_glob') {
                $matches = is_array($result['matches'] ?? null) ? $result['matches'] : [];
                foreach (array_slice($matches, 0, 20) as $match) {
                    if (! is_array($match)) {
                        continue;
                    }
                    $path = (string) ($match['path'] ?? '');
                    if ($path !== '') {
                        $out[] = ['tool' => $row->tool, 'path' => $path, 'found' => true];
                    }
                }

                continue;
            }

            $path = (string) ($result['path'] ?? $payload['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $preview = StringCoercion::toString($result['preview'] ?? null, '');
            $entry = [
                'tool' => 'file_read_safe',
                'path' => $path,
                'found' => (bool) ($result['found'] ?? true),
            ];
            if ($preview !== '') {
                $entry['preview'] = mb_strlen($preview) > 400
                    ? mb_substr($preview, 0, 399).'…'
                    : $preview;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  list<array{tool: string, path: string, found: bool}>  $toolEvidence
     */
    public static function hasReadEvidence(array $execResult, array $toolEvidence): bool
    {
        return self::countFilesRead($execResult) > 0 || $toolEvidence !== [];
    }

    /**
     * Slim preflight rows for the executor LLM prompt (no file previews).
     *
     * @param  list<array<string, mixed>>  $reads
     * @return array{_reads: list<array{path: string, found: bool, reason: string}>, _omitted: int}
     */
    public static function slimReadsForExecutorPrompt(array $reads, int $max = 12): array
    {
        $slim = [];
        foreach ($reads as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = StringCoercion::toString($item['path'] ?? null);
            if ($path === '') {
                continue;
            }
            $slim[] = [
                'path' => $path,
                'found' => (bool) ($item['found'] ?? true),
                'reason' => StringCoercion::toString($item['reason'] ?? null, 'read during preflight'),
            ];
        }

        $omitted = max(0, count($slim) - $max);
        if ($omitted > 0) {
            $slim = array_slice($slim, 0, $max);
        }

        $out = ['reads' => $slim];
        if ($omitted > 0) {
            $out['note'] = $omitted.' additional preflight read(s) omitted from this prompt to save context.';
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $preflightReads
     * @return list<array{path: string, preview: string}>
     */
    public static function readPreviewsForAudit(array $preflightReads, int $max = 5, int $previewChars = 800): array
    {
        $out = [];
        foreach ($preflightReads as $item) {
            if (! is_array($item) || count($out) >= $max) {
                continue;
            }
            $path = StringCoercion::toString($item['path'] ?? null);
            $preview = StringCoercion::toString($item['preview'] ?? null, '');
            if ($path === '' || $preview === '' || ($item['found'] ?? true) !== true) {
                continue;
            }
            $out[] = [
                'path' => $path,
                'preview' => mb_strlen($preview) > $previewChars
                    ? mb_substr($preview, 0, $previewChars - 1).'…'
                    : $preview,
            ];
        }

        return $out;
    }

    /**
     * Preflight reads with truncated previews for security / high-risk executor tasks.
     *
     * @param  list<array<string, mixed>>  $reads
     * @return array{reads: list<array{path: string, found: bool, reason: string, preview?: string}>, note?: string}
     */
    public static function readsWithPreviewForExecutorPrompt(array $reads, int $maxFiles = 8, int $charsPerFile = 1500): array
    {
        $out = [];
        foreach ($reads as $item) {
            if (! is_array($item) || count($out) >= $maxFiles) {
                continue;
            }
            $path = StringCoercion::toString($item['path'] ?? null);
            if ($path === '' || ($item['found'] ?? true) !== true) {
                continue;
            }
            $preview = StringCoercion::toString($item['preview'] ?? null, '');
            $row = [
                'path' => $path,
                'found' => true,
                'reason' => StringCoercion::toString($item['reason'] ?? null, 'read during preflight'),
            ];
            if ($preview !== '') {
                $row['preview'] = mb_strlen($preview) > $charsPerFile
                    ? mb_substr($preview, 0, $charsPerFile - 1).'…'
                    : $preview;
            }
            $out[] = $row;
        }

        $omitted = max(0, count(array_filter($reads, static fn ($r) => is_array($r))) - $maxFiles);
        $result = ['reads' => $out];
        if ($omitted > 0) {
            $result['note'] = $omitted.' additional read(s) omitted from this prompt.';
        }

        return $result;
    }

    public static function wantsPreviewReadsInExecutorPrompt(array $modelRoute, array $plan = []): bool
    {
        if (($modelRoute['needs_security_auditor'] ?? false) === true) {
            return true;
        }
        if (($modelRoute['risk_level'] ?? '') === 'high') {
            return true;
        }
        $skill = strtolower((string) ($plan['skill'] ?? $modelRoute['skill'] ?? ''));

        return str_contains($skill, 'security') || str_contains($skill, 'audit');
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  list<array<string, mixed>>  $preflightReads
     * @return array<string, mixed>
     */
    public static function executorPayloadForAudit(
        array $execResult,
        array $preflightReads = [],
        ?string $runId = null,
        int $previewMaxFiles = 5,
    ): array {
        $payload = [
            'status' => $execResult['status'] ?? null,
            'patch_summary' => $execResult['patch_summary'] ?? null,
            'files_read' => $execResult['files_read'] ?? [],
            'files_changed' => $execResult['files_changed'] ?? [],
            'commands_run' => $execResult['commands_run'] ?? [],
            'read_previews' => self::readPreviewsForAudit(
                $preflightReads,
                $previewMaxFiles,
                (int) config('bossku.audit_preview_chars', 800),
            ),
            'tool_evidence' => self::toolEvidenceForRun($runId),
            'proof_files' => self::proofFilePaths($execResult),
        ];

        if (($execResult['status'] ?? '') === 'failed') {
            $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
            $payload['executor_error'] = is_string($issues[0] ?? null)
                ? $issues[0]
                : StringCoercion::toString($issues[0] ?? null, '');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $audit
     * @param  array<string, mixed>  $execResult
     * @param  list<array<string, mixed>>  $preflightReads
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>|null
     */
    public static function rejectedFilesPayloadForRevision(
        array $execResult,
        string $runId,
        string $userFeedback = '',
    ): ?array {
        $rejected = \App\Models\BosskuAi\Approval::query()
            ->where('run_id', $runId)
            ->where('operation_type', 'file_write')
            ->where('status', 'rejected')
            ->orderBy('created_at')
            ->get();

        if ($rejected->isEmpty()) {
            return null;
        }

        $items = [];
        foreach ($rejected as $approval) {
            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $path = StringCoercion::toString($evidence['path'] ?? null, '');
            if ($path === '') {
                continue;
            }
            $items[] = [
                'path' => $path,
                'change_type' => StringCoercion::toString($evidence['change_type'] ?? null, 'modified'),
                'before' => (string) ($evidence['before'] ?? ''),
                'user_note' => trim((string) ($approval->decision_note ?? '')),
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'revision_type' => 'rejected_file_writes',
            'rejected_approvals' => $items,
            'user_approval_feedback' => $userFeedback,
            'required_actions' => [
                'Revert every rejected path to its exact before snapshot (or delete if change_type was created).',
                'Prefer `git restore <path>` in commands_run when the repo is under git.',
                'Do not re-apply the rejected after content.',
                'List each reverted path in patch_summary after verification.',
            ],
            'executor_result' => self::executorPayloadForAudit($execResult, [], $runId),
        ];
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  list<array{path: string, change_type: string, before: string, user_note: string}>  $rejectedItems
     * @return array<string, mixed>
     */
    public static function userCodeReviewPayloadForRevision(
        array $execResult,
        string $runId,
        string $codeReviewInstructions,
        array $rejectedItems = [],
        string $userContext = '',
        array $preflightReads = [],
    ): array {
        return [
            'revision_type' => 'user_code_review',
            'code_review_instructions' => $codeReviewInstructions,
            'rejected_items' => $rejectedItems,
            'user_approval_feedback' => $userContext,
            'required_actions' => [
                'Apply the user code review instructions exactly.',
                'For each affected path, supply complete `after` file contents or a valid unified `diff`.',
                'Do not re-propose rejected content unchanged; address every instruction.',
                'List updated paths in patch_summary; user must approve before files are written.',
            ],
            'executor_result' => self::executorPayloadForAudit($execResult, $preflightReads, $runId),
            'read_previews' => self::readPreviewsForAudit($preflightReads),
            'tool_evidence' => self::toolEvidenceForRun($runId),
            'proof_files' => self::proofFilePaths($execResult),
        ];
    }

    public static function auditorPayloadForRevision(
        array $audit,
        array $execResult,
        array $preflightReads = [],
        ?string $runId = null,
    ): array {
        return [
            'original_prompt' => null,
            'audit' => [
                'status' => $audit['status'] ?? null,
                'summary' => $audit['summary'] ?? null,
                'findings' => $audit['findings'] ?? [],
                'required_fixes' => $audit['required_fixes'] ?? [],
                'optional_improvements' => $audit['optional_improvements'] ?? [],
            ],
            'executor_result' => self::executorPayloadForAudit($execResult, $preflightReads, $runId),
            'read_previews' => self::readPreviewsForAudit($preflightReads),
            'tool_evidence' => self::toolEvidenceForRun($runId),
            'proof_files' => self::proofFilePaths($execResult),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    public static function proofFilePaths(array $result): array
    {
        $paths = [];
        foreach (['files_read', 'files_changed'] as $key) {
            $items = is_array($result[$key] ?? null) ? $result[$key] : [];
            foreach ($items as $item) {
                if (is_string($item) && $item !== '') {
                    $paths[$item] = true;

                    continue;
                }
                if (is_array($item)) {
                    $path = StringCoercion::toString($item['path'] ?? null);
                    if ($path !== '') {
                        $paths[$path] = true;
                    }
                }
            }
        }

        return array_keys($paths);
    }

    /**
     * @return array<string, mixed>
     */
    public static function deterministicExecutorFailed(string $reason = ''): array
    {
        $summary = $reason !== ''
            ? $reason
            : 'Executor could not produce valid JSON output. Check model settings (Settings → Models) or retry the run.';

        return [
            'status' => 'failed',
            'summary' => $summary,
            'findings' => [],
            'required_fixes' => [],
            'optional_improvements' => [],
            'risk_level' => 'medium',
            'requires_security_audit' => false,
            'requires_final_reviewer' => true,
            'final_output' => $summary,
            '_legacy_pass' => false,
            '_deterministic' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deterministicNoFilesRead(string $reason = ''): array
    {
        $summary = $reason !== '' ? $reason : 'No repository files were read during this run. Register and activate the project under Project → Paths, ensure the repo is mounted in Docker (/workspace), then retry the audit.';

        return [
            'status' => 'revise',
            'summary' => $summary,
            'security_issues' => [],
            '_deterministic' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function deterministicNoReadableContent(string $reason = ''): array
    {
        $summary = $reason !== ''
            ? $reason
            : 'Files were listed as present but no readable content was captured. Check the active project mount (/workspace), file permissions, and that preflight file_read_safe returned previews.';

        return [
            'status' => 'revise',
            'summary' => $summary,
            'security_issues' => [],
            '_deterministic' => true,
        ];
    }
}
