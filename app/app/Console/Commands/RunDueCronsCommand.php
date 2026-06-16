<?php

namespace App\Console\Commands;

use App\Models\BosskuAi\CronJob;
use App\Services\Kernel\Platform\CronService;
use Illuminate\Console\Command;

/**
 * Marks due assistant cron jobs as ran and advances their next run. Actual
 * dispatch of the assistant run is wired once the live node adapters land
 * (Phase 1 increment 2); until then this records scheduling state so the cadence
 * is observable. Scheduled every minute (see bootstrap/app.php).
 */
class RunDueCronsCommand extends Command
{
    protected $signature = 'bossku:run-due-crons';

    protected $description = 'Process due BosskuAI assistant cron jobs.';

    public function handle(CronService $crons): int
    {
        $due = $crons->due();

        if ($due->isEmpty()) {
            $this->info('No cron jobs due.');

            return self::SUCCESS;
        }

        $due->each(function (CronJob $job) use ($crons): void {
            // Dispatch hook: once node adapters land, enqueue an assistant run here.
            $crons->markRan($job);
            $this->line("Marked cron '{$job->name}' ({$job->id}) as ran; next at {$job->next_run_at}.");
        });

        $this->info("Processed {$due->count()} due cron job(s).");

        return self::SUCCESS;
    }
}
