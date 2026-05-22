<?php

namespace App\Services;

use App\Models\Run;
use App\Models\RunAction;
use Illuminate\Support\Facades\Log;

class OrchestratorService
{
    // ... existing code ...

    /**
     * Collect executor evidence after a run action.
     */
    public function collectEvidence(RunAction $action, array $executorOutput): void
    {
        $evidence = [
            'run_id' => $action->run_id,
            'action_id' => $action->id,
            'files_read' => $executorOutput['files_read'] ?? [],
            'files_changed' => $executorOutput['files_changed'] ?? [],
            'commands_run' => $executorOutput['commands_run'] ?? [],
            'tests_run' => $executorOutput['tests_run'] ?? [],
            'patch_summary' => $executorOutput['patch_summary'] ?? '',
            'timestamp' => now()->toIso8601String(),
        ];

        // Store evidence in a dedicated log or database table
        Log::channel('executor_evidence')->info('Executor evidence', $evidence);

        // Optionally persist to a structured evidence store
        // Evidence::create($evidence);
    }

    /**
     * Attempt to rollback the last executor action.
     * Currently a placeholder; implement git restore or file revert logic.
     */
    public function rollbackLastAction(Run $run): bool
    {
        $lastAction = $run->actions()->latest()->first();
        if (!$lastAction || $lastAction->type !== 'executor') {
            return false;
        }

        // Placeholder: in production, use git restore or revert file changes
        Log::warning('Rollback requested but not implemented', [
            'run_id' => $run->id,
            'action_id' => $lastAction->id,
        ]);

        return false;
    }
}