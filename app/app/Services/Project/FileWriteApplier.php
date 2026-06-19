<?php

namespace App\Services\Project;

use App\Models\BosskuAi\Approval;
use App\Services\Governance\ProposedFileChangeGuard;
use Illuminate\Support\Facades\File;

class FileWriteApplier
{
    public function __construct(
        private readonly ProjectPathResolver $paths,
        private readonly WorkspaceWriteGuard $writeGuard,
        private readonly ProposedFileChangeGuard $fileChangeGuard,
    ) {}

    /**
     * @return array{path: string, relative: string}
     */
    public function applyPath(string $relativePath, string $after, string $changeType = 'modified'): array
    {
        if ($changeType === 'deleted') {
            $resolved = $this->paths->resolve($relativePath);
            if (is_file($resolved['absolute'])) {
                unlink($resolved['absolute']);
            }

            return ['path' => $resolved['absolute'], 'relative' => $resolved['relative']];
        }

        $resolved = $this->paths->resolveForWrite($relativePath);

        if (is_dir($resolved['absolute'])) {
            throw new \RuntimeException('Cannot write file — target path is a directory: '.$resolved['relative']);
        }

        $this->writeGuard->ensureWritable($resolved['absolute'], $this->paths->repoRoot());

        if (file_put_contents($resolved['absolute'], $after) === false) {
            throw new \RuntimeException('Failed to write file: '.$resolved['relative']);
        }

        return ['path' => $resolved['absolute'], 'relative' => $resolved['relative']];
    }

    /**
     * @return array{path: string, relative: string}
     */
    public function applyApproval(Approval $approval): array
    {
        if ($approval->operation_type !== 'file_write') {
            throw new \InvalidArgumentException('Not a file write approval.');
        }

        if (! in_array($approval->status, ['approved', 'auto_approved'], true)) {
            throw new \InvalidArgumentException('Approval must be approved before applying.');
        }

        /** @var array<string, mixed> $evidence */
        $evidence = is_array($approval->evidence) ? $approval->evidence : [];
        $path = (string) ($evidence['path'] ?? '');
        $after = (string) ($evidence['after'] ?? '');
        $changeType = (string) ($evidence['change_type'] ?? 'modified');

        if ($path === '') {
            throw new \InvalidArgumentException('Missing file path in approval evidence.');
        }

        $before = (string) ($evidence['before'] ?? '');
        if ($before === '' && $changeType !== 'created') {
            try {
                $resolved = $this->paths->resolve($path);
                if (is_file($resolved['absolute'])) {
                    $before = (string) file_get_contents($resolved['absolute']);
                }
            }
            catch (\Throwable) {
                // use empty before
            }
        }

        $rejectReason = $this->fileChangeGuard->validate($before, $after, $changeType, $path);
        if ($rejectReason !== null) {
            throw new \RuntimeException('Refusing to apply file change: '.$rejectReason);
        }

        $written = $this->applyPath($path, $after, $changeType);

        $approval->update([
            'metadata' => array_merge(is_array($approval->metadata) ? $approval->metadata : [], [
                'applied_at' => now()->toIso8601String(),
            ]),
        ]);

        return $written;
    }

    /**
     * Apply a unified diff to $before and return the patched content, or null
     * when the diff is malformed or does not match the file.
     *
     * Hunk-aware: honours `@@ -a,b +c,d @@` headers, verifies context and
     * removed lines against the actual file, and preserves all content outside
     * the hunks. Hunk offsets are treated as hints — if the context does not
     * match at the declared line, the hunk body is located by content from the
     * current cursor forward (LLM diffs frequently carry stale line numbers
     * with correct context). Header-less diffs are treated as one implicit
     * hunk located by content.
     */
    public function applyUnifiedDiff(string $before, string $diff): ?string
    {
        $diff = trim($diff);
        if ($diff === '') {
            return null;
        }

        $hunks = $this->parseUnifiedDiffHunks($diff);
        if ($hunks === null || $hunks === []) {
            return null;
        }

        $oldLines = preg_split("/\r\n|\n|\r/", $before) ?: [];
        $hadTrailingNewline = $before !== '' && (str_ends_with($before, "\n") || str_ends_with($before, "\r"));
        if ($hadTrailingNewline && $oldLines !== [] && end($oldLines) === '') {
            array_pop($oldLines);
        }
        if ($before === '') {
            $oldLines = [];
        }

        $out = [];
        $cursor = 0;

        foreach ($hunks as $hunk) {
            $expectedOld = $hunk['old'];

            if ($expectedOld === []) {
                // Pure-insertion hunk: anchor on the header position when sane,
                // otherwise append at the end of the remaining content.
                $insertAt = $hunk['old_start'] !== null
                    ? min(max($hunk['old_start'], $cursor), count($oldLines))
                    : count($oldLines);
                $matchPos = $insertAt;
            } else {
                $matchPos = $this->locateHunk($oldLines, $expectedOld, $cursor, $hunk['old_start']);
                if ($matchPos === null) {
                    return null;
                }
            }

            for ($i = $cursor; $i < $matchPos; $i++) {
                $out[] = $oldLines[$i];
            }
            $cursor = $matchPos;

            foreach ($hunk['ops'] as [$op, $payload]) {
                if ($op === '+') {
                    $out[] = $payload;

                    continue;
                }
                // Context and removals consume a verified old line; context is kept.
                if ($op === ' ') {
                    $out[] = $oldLines[$cursor];
                }
                $cursor++;
            }
        }

        for ($i = $cursor; $i < count($oldLines); $i++) {
            $out[] = $oldLines[$i];
        }

        $result = implode("\n", $out);
        if ($hadTrailingNewline || ($before === '' && $result !== '')) {
            $result .= "\n";
        }

        return $result;
    }

    /**
     * @return list<array{old_start: int|null, old: list<string>, ops: list<array{0: string, 1: string}>}>|null
     */
    protected function parseUnifiedDiffHunks(string $diff): ?array
    {
        $lines = preg_split("/\r\n|\n|\r/", $diff) ?: [];
        $hunks = [];
        $current = null;
        $sawHunkHeader = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')
                || str_starts_with($line, 'diff ') || str_starts_with($line, 'index ')
                || str_starts_with($line, '\\')) {
                continue;
            }

            if (preg_match('/^\s*@@\s*-(\d+)(?:,(\d+))?\s+\+(\d+)(?:,\d+)?\s*@@/', $line, $m) === 1) {
                $sawHunkHeader = true;
                if ($current !== null) {
                    $hunks[] = $current;
                }
                // 0-based content index. For `-N,0` (pure insertion) the diff
                // convention is "insert after line N", so the index is N itself.
                $oldStart = (int) $m[1];
                $oldCount = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1;
                $current = [
                    'old_start' => $oldCount === 0 ? $oldStart : max(0, $oldStart - 1),
                    'old' => [],
                    'ops' => [],
                ];

                continue;
            }

            if ($current === null) {
                // Header-less diff: implicit single hunk located purely by content.
                if ($line === '') {
                    continue;
                }
                $current = ['old_start' => null, 'old' => [], 'ops' => []];
            }

            $prefix = $line === '' ? ' ' : $line[0];
            $payload = $line === '' ? '' : substr($line, 1);

            if ($prefix === ' ' || $prefix === '-') {
                $current['old'][] = $payload;
                $current['ops'][] = [$prefix, $payload];
            } elseif ($prefix === '+') {
                $current['ops'][] = ['+', $payload];
            } else {
                return null;
            }
        }

        if ($current !== null) {
            $hunks[] = $current;
        }

        // A diff with neither hunk headers nor file headers is too ambiguous to
        // trust unless it actually verifies against content (handled by caller),
        // but require at least some structure: file headers or @@ markers.
        if (! $sawHunkHeader && ! str_contains($diff, '--- ') && ! str_contains($diff, '+++ ')) {
            return null;
        }

        return $hunks;
    }

    /**
     * Find where a hunk's expected old lines occur. Tries the declared header
     * position first (exact, then whitespace-relaxed), then scans forward from
     * the cursor, accepting only an unambiguous content match.
     *
     * @param  list<string>  $oldLines
     * @param  list<string>  $expectedOld
     */
    protected function locateHunk(array $oldLines, array $expectedOld, int $cursor, ?int $declaredStart): ?int
    {
        if ($declaredStart !== null
            && $declaredStart >= $cursor
            && $this->hunkMatchesAt($oldLines, $expectedOld, $declaredStart)) {
            return $declaredStart;
        }

        $matches = [];
        $limit = count($oldLines) - count($expectedOld);
        for ($pos = $cursor; $pos <= $limit; $pos++) {
            if ($this->hunkMatchesAt($oldLines, $expectedOld, $pos)) {
                $matches[] = $pos;
                if (count($matches) > 1) {
                    return null;
                }
            }
        }

        return $matches[0] ?? null;
    }

    /**
     * @param  list<string>  $oldLines
     * @param  list<string>  $expectedOld
     */
    protected function hunkMatchesAt(array $oldLines, array $expectedOld, int $pos): bool
    {
        if ($pos < 0 || $pos + count($expectedOld) > count($oldLines)) {
            return false;
        }

        foreach ($expectedOld as $i => $expected) {
            $actual = $oldLines[$pos + $i];
            if ($actual !== $expected && rtrim($actual) !== rtrim($expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function extractAfterContent(array $item, ?string $relativePath = null): ?string
    {
        foreach (['after', 'new_contents', 'contents'] as $key) {
            $value = $item[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $path = (string) ($relativePath ?? $item['path'] ?? '');
        if ($path === '') {
            return null;
        }

        $diff = $item['diff'] ?? null;
        if (! is_string($diff) || trim($diff) === '') {
            return null;
        }

        try {
            $resolved = $this->paths->resolve($path);
            $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
        }
        catch (\Throwable) {
            return null;
        }

        return $this->applyUnifiedDiff($before, $diff);
    }
}
