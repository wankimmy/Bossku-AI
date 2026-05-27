<?php

namespace App\Jobs;

use App\Services\Learning\LearningEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessLearningEventJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(
        public string $learningEventId,
    ) {}

    public function uniqueId(): string
    {
        return $this->learningEventId;
    }

    public function handle(LearningEventProcessor $processor): void
    {
        try {
            $processor->processEvent($this->learningEventId, 'auto');
        } catch (\Throwable $e) {
            Log::warning('ProcessLearningEventJob failed', [
                'learning_event_id' => $this->learningEventId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
