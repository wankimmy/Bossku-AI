<?php

namespace App\Jobs;

use App\Models\BosskuAi\AgentWakeupRequest;
use App\Services\Company\AgentWakeupDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAgentWakeupRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $wakeupRequestId,
    ) {}

    public function handle(AgentWakeupDispatcher $dispatcher): void
    {
        $request = AgentWakeupRequest::query()->find($this->wakeupRequestId);
        if ($request === null) {
            return;
        }

        try {
            $dispatcher->processRequest($request);
        } catch (\Throwable $e) {
            Log::warning('bossku.agent_wakeup.failed', [
                'wakeup_request_id' => $this->wakeupRequestId,
                'error' => $e->getMessage(),
            ]);
            $request->update([
                'status' => 'failed',
                'skip_reason' => $e->getMessage(),
                'processed_at' => now(),
            ]);
        }
    }
}
