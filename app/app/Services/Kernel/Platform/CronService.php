<?php

namespace App\Services\Kernel\Platform;

use App\Models\BosskuAi\CronJob;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Schedule logic for assistant cron jobs. Determines which jobs are due for the
 * current minute and computes their next run. Dispatch of the actual assistant
 * run is performed by the runner command once node adapters land.
 */
final class CronService
{
    public function isValidExpression(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }

    public function isDue(CronJob $job, ?CarbonInterface $now = null): bool
    {
        if (! $job->enabled) {
            return false;
        }
        $now ??= Carbon::now();

        return (new CronExpression($job->cron_expression))->isDue($now->copy());
    }

    public function nextRun(CronJob $job, ?CarbonInterface $from = null): Carbon
    {
        $from ??= Carbon::now();
        $next = (new CronExpression($job->cron_expression))->getNextRunDate($from->copy());

        return Carbon::instance($next);
    }

    /**
     * Enabled jobs that are due to run at $now.
     *
     * @return Collection<int, CronJob>
     */
    public function due(?CarbonInterface $now = null): Collection
    {
        $now ??= Carbon::now();

        return CronJob::query()
            ->where('enabled', true)
            ->get()
            ->filter(fn (CronJob $job): bool => $this->isDue($job, $now))
            ->values();
    }

    public function markRan(CronJob $job, ?CarbonInterface $now = null): void
    {
        $now ??= Carbon::now();
        $job->update([
            'last_run_at' => $now,
            'next_run_at' => $this->nextRun($job, $now),
        ]);
    }
}
