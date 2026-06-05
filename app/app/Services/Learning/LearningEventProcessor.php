<?php

namespace App\Services\Learning;

use App\Jobs\ProcessLearningEventJob;
use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Support\Facades\Log;

class LearningEventProcessor
{
    public function __construct(
        protected LearningEventPromoter $promoter,
        protected RuntimeSettings $settings,
    ) {}

    public function dispatchForEvent(LearningEvent $event): void
    {
        if (! $this->isAutoPromoteEligible($event)) {
            return;
        }

        ProcessLearningEventJob::dispatch($event->getKey());
    }

    public function processEvent(string $id, string $promotionMode = 'auto'): ?Memory
    {
        $event = LearningEvent::query()->find($id);
        if ($event === null) {
            return null;
        }

        if ($event->status === 'rejected') {
            return null;
        }

        return $this->promoter->promote($event->fresh(), $promotionMode);
    }

    /**
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function processPendingBatch(?int $limit = null): array
    {
        $limit = $limit ?? $this->settings->learningBatchSize();
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $events = LearningEvent::query()
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            if (! $this->isAutoPromoteEligible($event)) {
                $skipped++;

                continue;
            }

            try {
                $memory = $this->processEvent($event->getKey(), 'auto');
                if ($memory !== null) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                Log::warning('Learning event promotion failed', [
                    'learning_event_id' => $event->getKey(),
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    public function isAutoPromoteEligible(LearningEvent $event): bool
    {
        if (! $this->settings->memoryStorageEnabled()) {
            return false;
        }

        if (! $this->settings->learningAutoPromoteEnabled()) {
            return false;
        }

        if ($event->status !== 'pending') {
            return false;
        }

        if (! in_array($event->type, $this->settings->learningAutoPromoteTypes(), true)) {
            return false;
        }

        if ((float) ($event->confidence ?? 0) < $this->settings->learningAutoPromoteMinConfidence()) {
            return false;
        }

        if ((bool) config('bossku.learning_require_verification', true)) {
            $evidence = is_array($event->evidence) ? $event->evidence : [];
            if (isset($evidence['feedback_report_id'])) {
                $report = \App\Models\BosskuAi\FeedbackReport::query()->find($evidence['feedback_report_id']);

                return $report !== null && $report->verified;
            }

            if (! empty($evidence['verification_passed'])) {
                return true;
            }

            return false;
        }

        return true;
    }
}
