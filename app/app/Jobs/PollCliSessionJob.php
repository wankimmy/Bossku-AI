<?php

namespace App\Jobs;

use App\Models\BosskuAi\CliSession;
use App\Services\Providers\CliSessionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollCliSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 120;

    public function __construct(
        public readonly string $sessionId,
        public readonly int $attempt = 0,
    ) {}

    public function handle(CliSessionService $sessions): void
    {
        $session = CliSession::query()->find($this->sessionId);
        if ($session === null || $session->status !== 'running') {
            return;
        }

        $stillRunning = $sessions->refreshRunningSession($session);
        if ($stillRunning) {
            self::dispatch($this->sessionId, $this->attempt + 1)
                ->delay(now()->addSeconds(5));

            return;
        }

        $meta = is_array($session->metadata) ? $session->metadata : [];
        if (($meta['poll_attempts'] ?? 0) < $this->attempt) {
            $session->update([
                'metadata' => array_merge($meta, ['poll_attempts' => $this->attempt]),
            ]);
        }
    }
}
