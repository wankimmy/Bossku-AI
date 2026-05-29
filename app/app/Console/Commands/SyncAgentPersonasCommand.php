<?php

namespace App\Console\Commands;

use App\Services\BosskuAi\AgentPersonaService;
use Illuminate\Console\Command;

class SyncAgentPersonasCommand extends Command
{
    protected $signature = 'bosskuai:sync-personas
                            {--force : Overwrite ALL pipeline persona rows from agents/*.md, including user-edited rows}
                            {--dry-run : Show what would change without writing to the database}
                            {--role=* : Limit sync to specific roles (e.g. --role=orchestrator --role=auditor)}';

    protected $description = 'Sync pipeline agent personas from agents/*.md into the database';

    public function handle(AgentPersonaService $service): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce  = (bool) $this->option('force');
        $roles    = (array) $this->option('role') ?: null;

        if ($isDryRun) {
            $this->line('<fg=yellow>Dry-run mode — no changes will be written.</>');
        }

        if ($isForce) {
            $report = $service->syncPersonasFromMd($roles, $isDryRun);
        } else {
            // Standard path: only upgrades stubs and md_hash-tracked changed rows.
            // Rows without md_hash that have real content are left alone.
            $service->ensurePipelinePersonas();
            $report = $service->syncPersonasFromMd($roles, true); // dry-run to produce the report
            $this->line('<fg=cyan>Standard sync complete (stubs + md_hash-tracked changes).</>');
            $this->line('Use <fg=yellow>--force</> to overwrite all rows from .md, including user-edited content.');
            $this->renderReport($service->syncPersonasFromMd($roles, false));

            return self::SUCCESS;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    /** @param list<array{role: string, action: string, old_preview: string, new_preview: string}> $report */
    private function renderReport(array $report): void
    {
        $headers = ['Role', 'Action', 'Old (preview)', 'New (preview)'];
        $rows = array_map(fn (array $r) => [
            $r['role'],
            $r['action'],
            mb_strimwidth($r['old_preview'], 0, 55, '…'),
            mb_strimwidth($r['new_preview'], 0, 55, '…'),
        ], $report);

        $this->table($headers, $rows);

        $updated = count(array_filter($report, fn ($r) => in_array($r['action'], ['updated', 'created', 'would_update'], true)));
        $unchanged = count(array_filter($report, fn ($r) => $r['action'] === 'unchanged'));

        $this->info("Updated: {$updated}  |  Unchanged: {$unchanged}  |  Total: ".count($report));
    }
}
