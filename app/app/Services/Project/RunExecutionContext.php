<?php

namespace App\Services\Project;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;

/**
 * Request-scoped binding of the active run for path/command resolution.
 */
class RunExecutionContext
{
    private ?string $runId = null;

    public function bind(?string $runId): void
    {
        $this->runId = $runId;
    }

    public function clear(): void
    {
        $this->runId = null;
    }

    public function runId(): ?string
    {
        return $this->runId;
    }

    public function workspaceForRun(?string $runId = null): ?RunWorkspace
    {
        $id = $runId ?? $this->runId;
        if ($id === null || $id === '') {
            return null;
        }

        $workspace = RunWorkspace::query()
            ->where('run_id', $id)
            ->where('status', 'ready')
            ->first();

        return $workspace instanceof RunWorkspace ? $workspace : null;
    }

    public function executionContext(ProjectPathResolver $paths, ?string $runId = null): ExecutionContext
    {
        $id = $runId ?? $this->runId;
        $workspace = $this->workspaceForRun($id);
        if ($workspace !== null) {
            $real = realpath($workspace->worktree_path);
            if ($real !== false && is_dir($real)) {
                $meta = is_array($workspace->metadata) ? $workspace->metadata : [];
                $mode = (string) ($meta['execution_mode'] ?? 'local');

                return new ExecutionContext(
                    repoRoot: $real,
                    runId: $id,
                    mode: $mode,
                    remoteHost: is_string($meta['remote_host'] ?? null) ? $meta['remote_host'] : null,
                );
            }
        }

        return ExecutionContext::fromRepoRoot($paths->repoRootWithoutRun(), $id);
    }
}
