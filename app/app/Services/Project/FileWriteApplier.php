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
        $resolved = $this->paths->resolve($relativePath);

        if ($changeType === 'deleted') {
            if (is_file($resolved['absolute'])) {
                unlink($resolved['absolute']);
            }

            return ['path' => $resolved['absolute'], 'relative' => $resolved['relative']];
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
     * Best-effort patch when the model only returned a unified diff.
     */
    public function applyUnifiedDiff(string $before, string $diff): ?string
    {
        $diff = trim($diff);
        if ($diff === '') {
            return null;
        }

        if (! str_contains($diff, "\n--- ") && ! str_starts_with($diff, '--- ')) {
            return null;
        }

        $lines = preg_split("/\r\n|\n|\r/", $diff) ?: [];
        $out = [];
        $oldLines = preg_split("/\r\n|\n|\r/", $before) ?: [];
        $oldIndex = 0;

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')) {
                continue;
            }

            $prefix = $line[0] ?? '';
            $payload = substr($line, 1);

            if ($prefix === ' ') {
                $out[] = $payload;
                $oldIndex++;

                continue;
            }

            if ($prefix === '+') {
                $out[] = $payload;

                continue;
            }

            if ($prefix === '-') {
                $oldIndex++;

                continue;
            }

            return null;
        }

        while ($oldIndex < count($oldLines)) {
            $out[] = $oldLines[$oldIndex];
            $oldIndex++;
        }

        return implode("\n", $out);
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
