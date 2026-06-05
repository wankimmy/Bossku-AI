<?php

namespace App\Jobs;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\RunSupervisorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChildRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $childRunId,
    ) {}

    public function handle(OrchestratorService $orchestrator, RunSupervisorService $supervisor): void
    {
        $child = Run::query()->find($this->childRunId);
        if ($child === null) {
            return;
        }

        $child->update(['status' => 'running']);

        try {
            $orchestrator->run(
                (string) $child->prompt,
                null,
                [],
                [
                    'existing_run_id' => (string) $child->getKey(),
                    'run_kind' => 'child',
                    'parent_run_id' => $child->parent_run_id,
                    'supervisor_slot' => $child->supervisor_slot,
                    'use_worktree' => true,
                    'workspace_intent' => is_array($child->metadata['workspace_intent'] ?? null)
                        ? $child->metadata['workspace_intent']
                        : [],
                    'metadata' => is_array($child->metadata) ? $child->metadata : [],
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('bossku.child_run.failed', [
                'run_id' => $child->getKey(),
                'error' => $e->getMessage(),
            ]);
            $child->update(['status' => 'failed']);
        }

        if ($child->parent_run_id !== null) {
            $parent = Run::query()->find($child->parent_run_id);
            if ($parent !== null) {
                $supervisor->maybeFinalizeParent($parent);
            }
        }
    }
}
