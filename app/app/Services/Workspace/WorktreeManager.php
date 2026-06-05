<?php

namespace App\Services\Workspace;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class WorktreeManager
{
    public function enabled(): bool
    {
        return (bool) config('bossku.worktree_enabled', true);
    }

    public function poolPath(string $projectRepoRoot): string
    {
        $subdir = (string) config('bossku.worktree_pool_subdir', '.bossku/worktrees');

        return rtrim($projectRepoRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($subdir, '/'));
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public function provisionForRun(Run $run, ?Project $project = null, array $intent = []): RunWorkspace
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Worktree isolation is disabled (BOSSKU_WORKTREE_ENABLED=false).');
        }

        $project = $project ?? Project::query()->where('is_active', true)->first();
        if ($project === null) {
            throw new \RuntimeException('No active project for worktree provisioning.');
        }

        $repoRoot = $this->resolveProjectRoot($project);
        $baseRef = (string) ($intent['base_ref'] ?? 'HEAD');
        $branch = (string) ($intent['branch_name'] ?? $this->defaultBranchName($run));

        $existing = RunWorkspace::query()->where('run_id', $run->getKey())->first();
        if ($existing !== null && $existing->status === 'ready' && is_dir($existing->worktree_path)) {
            return $existing;
        }

        $workspace = $existing ?? RunWorkspace::query()->create([
            'run_id' => $run->getKey(),
            'project_id' => $project->getKey(),
            'base_ref' => $baseRef,
            'branch_name' => $branch,
            'worktree_path' => '',
            'status' => 'pending',
            'metadata' => $intent,
        ]);

        try {
            $path = $this->createWorktree($repoRoot, $branch, $baseRef, (string) $run->getKey());
            $workspace->update([
                'worktree_path' => $path,
                'status' => 'ready',
                'metadata' => array_merge(is_array($workspace->metadata) ? $workspace->metadata : [], [
                    'provisioned_at' => now()->toIso8601String(),
                ]),
            ]);

            $meta = is_array($run->metadata) ? $run->metadata : [];
            $run->update([
                'metadata' => array_merge($meta, [
                    'workspace' => [
                        'branch_name' => $branch,
                        'worktree_path' => $path,
                        'base_ref' => $baseRef,
                    ],
                ]),
            ]);

            return $workspace->refresh();
        } catch (\Throwable $e) {
            $workspace->update([
                'status' => 'failed',
                'metadata' => array_merge(is_array($workspace->metadata) ? $workspace->metadata : [], [
                    'error' => $e->getMessage(),
                ]),
            ]);

            Log::warning('bossku.worktree.failed', [
                'run_id' => $run->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function removeForRun(Run $run): void
    {
        $workspace = RunWorkspace::query()->where('run_id', $run->getKey())->first();
        if ($workspace === null) {
            return;
        }

        $path = $workspace->worktree_path;
        if ($path !== '' && is_dir($path)) {
            $project = $workspace->project_id
                ? Project::query()->find($workspace->project_id)
                : null;
            $repoRoot = $project ? $this->resolveProjectRoot($project) : null;
            $this->removeWorktree($path, $repoRoot);
        }

        $workspace->update(['status' => 'removed']);
    }

    protected function createWorktree(string $repoRoot, string $branch, string $baseRef, string $runId): string
    {
        File::ensureDirectoryExists($this->poolPath($repoRoot));

        $safeBranch = $this->sanitizeBranch($branch);
        $directorySlug = $this->directorySlug($branch, $runId);
        $target = $this->poolPath($repoRoot).DIRECTORY_SEPARATOR.$directorySlug;
        $this->assertPathWithinPool($repoRoot, $target);

        if (is_dir($target)) {
            $real = realpath($target);
            if ($real !== false && $this->isValidWorktree($repoRoot, $real)) {
                $this->assertPathWithinPool($repoRoot, $real);

                return $real;
            }
            File::deleteDirectory($target);
        }

        $this->runGit($repoRoot, ['worktree', 'prune']);

        $create = $this->runGit($repoRoot, [
            'worktree', 'add', '-B', $safeBranch, $target, $baseRef,
        ]);

        if ($create->failed()) {
            throw new \RuntimeException('git worktree add failed: '.trim($create->errorOutput()));
        }

        $real = realpath($target);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Worktree path not found after creation: '.$target);
        }

        $this->assertPathWithinPool($repoRoot, $real);
        $this->copyPreservedFiles($repoRoot, $real);

        return $real;
    }

    protected function directorySlug(string $branch, string $runId): string
    {
        $slug = str_replace('/', '-', trim($branch));
        $slug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        if ($slug === '' || str_contains($slug, '..')) {
            $slug = 'run';
        }

        return substr($runId, 0, 8).'-'.$slug;
    }

    protected function assertPathWithinPool(string $repoRoot, string $path): void
    {
        $pool = realpath($this->poolPath($repoRoot));
        if ($pool === false) {
            throw new \RuntimeException('Worktree pool is not available.');
        }

        $parent = dirname($path);
        File::ensureDirectoryExists($parent);
        $candidate = realpath($parent);
        if ($candidate === false) {
            $candidate = $parent;
        }

        $normalizedPool = rtrim(str_replace('\\', '/', $pool), '/');
        $normalizedCandidate = rtrim(str_replace('\\', '/', $candidate), '/');
        if ($normalizedCandidate !== $normalizedPool && ! str_starts_with($normalizedCandidate.'/', $normalizedPool.'/')) {
            throw new \RuntimeException('Worktree path escapes the configured pool.');
        }
    }

    protected function removeWorktree(string $worktreePath, ?string $repoRoot = null): void
    {
        if (is_string($repoRoot) && is_dir($repoRoot)) {
            $this->runGit($repoRoot, ['worktree', 'remove', '--force', $worktreePath]);
        }

        if (is_dir($worktreePath)) {
            File::deleteDirectory($worktreePath);
        }
    }

    protected function isValidWorktree(string $repoRoot, string $path): bool
    {
        $result = $this->runGit($repoRoot, ['worktree', 'list', '--porcelain']);

        return $result->successful() && str_contains($result->output(), $path);
    }

    protected function copyPreservedFiles(string $repoRoot, string $worktreePath): void
    {
        $patterns = config('bossku.worktree_preserve_files', ['.env', '.env.example']);
        if (! is_array($patterns)) {
            return;
        }

        foreach ($patterns as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $src = $repoRoot.DIRECTORY_SEPARATOR.$name;
            $dst = $worktreePath.DIRECTORY_SEPARATOR.$name;
            if (is_file($src) && ! is_file($dst)) {
                File::copy($src, $dst);
            }
        }
    }

    /**
     * @param  list<string>  $args
     */
    protected function runGit(string $cwd, array $args): \Illuminate\Contracts\Process\ProcessResult
    {
        return Process::path($cwd)->run(array_merge(['git'], $args));
    }

    protected function resolveProjectRoot(Project $project): string
    {
        $root = $project->container_path ?: (string) config('bossku.repo_root');
        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            throw new \RuntimeException('Project repo root is not available: '.$root);
        }

        return $real;
    }

    protected function defaultBranchName(Run $run): string
    {
        $slug = Str::slug(Str::limit($run->prompt ?? 'task', 40, ''));

        return 'bossku/'.substr((string) $run->getKey(), 0, 8).'-'.($slug !== '' ? $slug : 'run');
    }

    protected function sanitizeBranch(string $branch): string
    {
        $branch = trim(str_replace(['\\', ' '], ['/', '-'], $branch));
        if (str_contains($branch, '..')) {
            throw new \InvalidArgumentException('Branch name must not contain "..".');
        }
        $branch = preg_replace('/[^a-zA-Z0-9._\/-]+/', '-', $branch) ?? $branch;

        return trim($branch, '-/') ?: 'bossku/run';
    }
}
