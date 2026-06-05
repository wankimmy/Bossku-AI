<?php

namespace App\Services\Project;

/**
 * Scoped execution root for path resolution and command cwd.
 */
final class ExecutionContext
{
    public function __construct(
        public readonly string $repoRoot,
        public readonly ?string $runId = null,
        public readonly string $mode = 'local', // local|ssh|byoi
        public readonly ?string $remoteHost = null,
    ) {}

    public static function fromRepoRoot(string $repoRoot, ?string $runId = null): self
    {
        return new self(repoRoot: $repoRoot, runId: $runId, mode: 'local');
    }

    public function isRemote(): bool
    {
        return $this->mode === 'ssh' || $this->mode === 'byoi';
    }
}
