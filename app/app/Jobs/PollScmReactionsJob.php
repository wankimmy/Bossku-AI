<?php

namespace App\Jobs;

use App\Models\BosskuAi\Run;
use App\Services\Scm\ReactionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollScmReactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ReactionEngine $engine): void
    {
        $watchStatuses = (array) config('bossku.reactions_watch_statuses', ['running', 'paused', 'completed', 'partial']);

        Run::query()
            ->whereIn('status', $watchStatuses)
            ->whereNotNull('metadata')
            ->orderByDesc('updated_at')
            ->limit((int) config('bossku.reactions_poll_batch_size', 50))
            ->get()
            ->each(function (Run $run) use ($engine): void {
                $meta = is_array($run->metadata) ? $run->metadata : [];
                if (! isset($meta['scm']['pull_number'])) {
                    return;
                }
                try {
                    $engine->pollRun($run);
                } catch (\Throwable $e) {
                    Log::warning('bossku.reaction.poll_failed', [
                        'run_id' => $run->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
