<?php

namespace App\Jobs;

use App\Models\BosskuAi\Run;
use App\Services\Workspace\WorktreeManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupRunWorktreeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $runId,
    ) {}

    public function handle(WorktreeManager $worktrees): void
    {
        if (! (bool) config('bossku.worktree_cleanup_on_complete', true)) {
            return;
        }

        $run = Run::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        try {
            $worktrees->removeForRun($run);
        } catch (\Throwable $e) {
            Log::warning('bossku.worktree.cleanup_failed', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
