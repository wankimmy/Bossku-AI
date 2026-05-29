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
            $this->line('<fg=yellow>Dry-run — no changes will be written.</>');
        }
        if ($isForce) {
            $this->line('<fg=yellow>Force — user-edited personas will be overwritten from agents/*.md.</>');
        }

        $report = $service->syncPersonasFromMd($roles, force: $isForce, dryRun: $isDryRun);
        $this->renderReport($report);

        if (! $isForce) {
            $this->line('Use <fg=yellow>--force</> to also overwrite personas edited in the UI.');
        }

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

        $writeActions = ['updated', 'created', 'hash_backfilled', 'would_update', 'would_backfill_hash'];
        $changed = count(array_filter($report, fn ($r) => in_array($r['action'], $writeActions, true)));
        $skipped = count(array_filter($report, fn ($r) => $r['action'] === 'skipped_user_edited'));
        $unchanged = count(array_filter($report, fn ($r) => $r['action'] === 'unchanged'));

        $this->info("Changed: {$changed}  |  Unchanged: {$unchanged}  |  Skipped (user-edited): {$skipped}  |  Total: ".count($report));
    }
}
