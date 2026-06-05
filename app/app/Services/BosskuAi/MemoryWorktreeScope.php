<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;
use Illuminate\Support\Collection;

class MemoryWorktreeScope
{
    public const META_KEY = 'scope_worktree_path';

    public function pathForRun(Run $run): ?string
    {
        $workspace = RunWorkspace::query()
            ->where('run_id', $run->getKey())
            ->where('status', 'ready')
            ->first();

        if ($workspace === null || $workspace->worktree_path === '') {
            return null;
        }

        $real = realpath($workspace->worktree_path);

        return $real !== false ? $real : $workspace->worktree_path;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function applyToMetadata(array $metadata, ?string $worktreePath): array
    {
        if ($worktreePath !== null && $worktreePath !== '') {
            $metadata[self::META_KEY] = $worktreePath;
        }

        return $metadata;
    }

    /**
     * Keep global memories (no scope) and memories scoped to the active worktree.
     *
     * @param  Collection<int, Memory>  $memories
     * @return Collection<int, Memory>
     */
    public function filter(Collection $memories, ?string $activeWorktreePath): Collection
    {
        if ($activeWorktreePath === null || $activeWorktreePath === '') {
            return $memories;
        }

        $active = $this->normalizePath($activeWorktreePath);

        return $memories->filter(function (Memory $memory) use ($active) {
            $meta = is_array($memory->metadata) ? $memory->metadata : [];
            $scoped = $meta[self::META_KEY] ?? null;
            if (! is_string($scoped) || $scoped === '') {
                return true;
            }

            return $this->normalizePath($scoped) === $active;
        })->values();
    }

    protected function normalizePath(string $path): string
    {
        $real = realpath($path);

        return str_replace('\\', '/', $real !== false ? $real : $path);
    }
}
