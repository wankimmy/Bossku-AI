<?php

namespace App\Services\Learning;

use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Support\Str;

class LearningEventPromoter
{
    public function __construct(
        protected MemoryService $memory,
        protected RuntimeSettings $settings,
    ) {}

    public function promote(LearningEvent $event, string $promotionMode = 'auto'): ?Memory
    {
        if (! $this->settings->memoryStorageEnabled()) {
            return null;
        }

        if ($event->status === 'rejected') {
            return null;
        }

        if ($event->status === 'applied') {
            return $this->existingMemory($event);
        }

        $existing = $this->findMemoryByLearningEventId($event->getKey());
        if ($existing !== null) {
            $this->finalizeEvent($event, $existing, $promotionMode);

            return $existing;
        }

        $memory = $this->storeFromEvent($event);
        if ($memory === null) {
            return null;
        }

        $this->finalizeEvent($event, $memory, $promotionMode);

        return $memory;
    }

    protected function existingMemory(LearningEvent $event): ?Memory
    {
        $meta = is_array($event->metadata) ? $event->metadata : [];
        $memoryId = $meta['memory_id'] ?? null;
        if (! is_string($memoryId) || $memoryId === '') {
            return null;
        }

        return Memory::query()->find($memoryId);
    }

    protected function findMemoryByLearningEventId(string $learningEventId): ?Memory
    {
        return Memory::query()
            ->where('source', 'learning_event')
            ->where('metadata->learning_event_id', $learningEventId)
            ->first();
    }

    protected function storeFromEvent(LearningEvent $event): ?Memory
    {
        $payload = [
            'learning_event_id' => $event->getKey(),
            'type' => $event->type,
            'content' => $event->content,
            'evidence' => $event->evidence,
            'run_id' => $event->run_id,
            'promoted_at' => now()->toIso8601String(),
        ];

        try {
            $memory = $this->memory->store(
                json_encode($payload, JSON_THROW_ON_ERROR),
                $this->memoryTypeForEvent($event),
                [
                    'learning_event_id' => $event->getKey(),
                    'run_id' => $event->run_id,
                    'learning_type' => $event->type,
                ],
                Str::limit((string) $event->content, 200),
                $this->tagsForEvent($event),
                'learning_event',
                $this->importanceForEvent($event),
                (float) ($event->confidence ?? 0.72),
            );

            if ($event->run_id !== null) {
                MemoryRunLink::query()->firstOrCreate(
                    ['memory_id' => $memory->id, 'run_id' => $event->run_id],
                    ['similarity_score' => 1.0],
                );
            }

            return $memory;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function finalizeEvent(LearningEvent $event, Memory $memory, string $promotionMode): void
    {
        $meta = is_array($event->metadata) ? $event->metadata : [];
        $event->update([
            'status' => 'applied',
            'reviewed_at' => $event->reviewed_at ?? now(),
            'metadata' => array_merge($meta, [
                'memory_id' => $memory->id,
                'promoted_at' => now()->toIso8601String(),
                'promotion_mode' => $promotionMode,
            ]),
        ]);
    }

    protected function memoryTypeForEvent(LearningEvent $event): string
    {
        return match ($event->type) {
            'pattern' => 'learned_pattern',
            'failure' => 'learned_failure',
            'correction' => 'learned_correction',
            'preference' => 'learned_preference',
            default => 'learned_lesson',
        };
    }

    /**
     * @return list<string>
     */
    protected function tagsForEvent(LearningEvent $event): array
    {
        $tags = ['learning', 'promoted', (string) $event->type];

        if ($event->run_id !== null) {
            $run = Run::query()->find($event->run_id);
            $runMeta = is_array($run?->metadata) ? $run->metadata : [];
            if (! empty($runMeta['bossku_toolkit'])) {
                $tags[] = 'bossku-toolkit';
            }
        }

        return array_values(array_unique($tags));
    }

    protected function importanceForEvent(LearningEvent $event): float
    {
        return match ($event->type) {
            'failure', 'correction' => 0.85,
            'pattern' => 0.65,
            default => 0.7,
        };
    }
}
