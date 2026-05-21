<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\ToolCall;

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
            $out[] = [
                'tool' => 'file_read_safe',
                'path' => $path,
                'found' => (bool) ($result['found'] ?? true),
            ];
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
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    public static function executorPayloadForAudit(array $execResult): array
    {
        return [
            'status' => $execResult['status'] ?? null,
            'patch_summary' => $execResult['patch_summary'] ?? null,
            'files_read' => $execResult['files_read'] ?? [],
            'files_changed' => $execResult['files_changed'] ?? [],
            'commands_run' => $execResult['commands_run'] ?? [],
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
}
