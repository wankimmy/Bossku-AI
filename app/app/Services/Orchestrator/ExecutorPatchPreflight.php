<?php

namespace App\Services\Orchestrator;

use App\Services\Project\FileWriteApplier;
use App\Services\Project\ProjectPathResolver;
use App\Support\StringCoercion;

/**
 * Deterministic patch validation between the executor and the auditor.
 *
 * Catches malformed or inapplicable file changes (missing content, elision
 * placeholders, conflict markers, diffs that do not apply to the current file)
 * without spending an LLM audit call. Problems found here become a
 * deterministic needs_revision verdict that feeds the existing audit→revise
 * loop with precise, actionable feedback.
 */
class ExecutorPatchPreflight
{
    public function __construct(
        private readonly FileWriteApplier $fileWrites,
        private readonly ProjectPathResolver $paths,
    ) {}

    /**
     * @param  array<string, mixed>  $execResult
     * @return list<string>
     */
    public function problems(array $execResult): array
    {
        $problems = [];
        $files = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
        $appliedReport = is_array($execResult['_files_applied'] ?? null) ? $execResult['_files_applied'] : [];
        $alreadyApplied = is_array($appliedReport['applied'] ?? null) ? $appliedReport['applied'] : [];

        foreach ($files as $item) {
            if (! is_array($item)) {
                continue;
            }

            $path = StringCoercion::toString($item['path'] ?? null);
            if ($path === '') {
                continue;
            }

            $changeType = StringCoercion::toString($item['change_type'] ?? null, 'modified');
            if ($changeType === 'deleted') {
                continue;
            }

            $after = StringCoercion::toString($item['after'] ?? $item['new_contents'] ?? $item['contents'] ?? null, '');
            $diff = StringCoercion::toString($item['diff'] ?? null, '');

            if ($after === '' && $diff === '') {
                $problems[] = $path.': claims a '.$changeType.' but carries neither `diff` nor `after` contents — return the real change.';

                continue;
            }

            if (ExecutorResponseParser::contentHasPlaceholders($after."\n".$diff)) {
                $problems[] = $path.': content contains placeholder/elision markers (e.g. "// ..." or "rest of file unchanged") — return complete content or a valid diff with only the changed hunks.';

                continue;
            }

            if ($after !== '' && preg_match('/^(<{7}|>{7}|={7})( |$)/m', $after) === 1) {
                $problems[] = $path.': `after` content contains merge-conflict markers — resolve them and resubmit.';

                continue;
            }

            if ($after === '' && $diff !== '' && ! in_array($path, $alreadyApplied, true)) {
                $verdict = $this->dryRunDiff($path, $diff);
                if ($verdict !== null) {
                    $problems[] = $verdict;
                }
            }
        }

        return $problems;
    }

    /**
     * Dry-run a diff-only change against the current file. Returns a problem
     * string when the diff is malformed or does not match, null when it applies
     * or when the file cannot be inspected (unmounted repo, denied path — those
     * are not the executor's formatting fault, so they are not flagged here).
     */
    protected function dryRunDiff(string $path, string $diff): ?string
    {
        try {
            $resolved = $this->paths->resolve($path);
            $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
        } catch (\Throwable) {
            return null;
        }

        if ($this->fileWrites->applyUnifiedDiff($before, $diff) === null) {
            return $path.': the unified diff does not apply to the current file (malformed hunks or stale context). Re-read the file and emit a fresh diff, or return the complete file in `after`.';
        }

        return null;
    }
}
