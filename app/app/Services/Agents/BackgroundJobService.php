<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\Run;
use Illuminate\Support\Facades\Cache;

/**
 * Tracks background subagent jobs so the parent run can query their state and
 * inject results as synthetic messages when they complete. Ported from
 * opencode's BackgroundJob service (packages/opencode/src/tool/task.ts).
 *
 * The store is cache-backed (Redis in production, array in tests). Each job
 * has: task_id, parent_run_id, state (running/completed/error), result, and
 * timestamps. The parent polls nothing — the orchestrator checks this store
 * on the parent's next turn and injects completed results.
 */
final class BackgroundJobService
{
    private const CACHE_PREFIX = 'bossku:bg_job:';

    /** @var array<string, mixed>|null */
    private ?array $memoryStore = null;

    public function start(string $taskId, string $parentRunId): void
    {
        $this->put($taskId, [
            'task_id' => $taskId,
            'parent_run_id' => $parentRunId,
            'state' => 'running',
            'result' => null,
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
        ]);
    }

    public function complete(string $taskId, string $result): void
    {
        $job = $this->get($taskId);
        if ($job === null) {
            return;
        }
        $job['state'] = 'completed';
        $job['result'] = $result;
        $job['completed_at'] = now()->toIso8601String();
        $this->put($taskId, $job);
    }

    public function fail(string $taskId, string $error): void
    {
        $job = $this->get($taskId);
        if ($job === null) {
            return;
        }
        $job['state'] = 'error';
        $job['result'] = $error;
        $job['completed_at'] = now()->toIso8601String();
        $this->put($taskId, $job);
    }

    /** @return array<string, mixed>|null */
    public function get(string $taskId): ?array
    {
        if ($this->memoryStore !== null) {
            return $this->memoryStore[$taskId] ?? null;
        }

        $data = Cache::get(self::CACHE_PREFIX.$taskId);

        return is_array($data) ? $data : null;
    }

    /** @return list<array<string, mixed>> jobs for a parent run that are completed/error and not yet delivered */
    public function pendingForParent(string $parentRunId): array
    {
        // Cache-backed: scan is not practical for Redis; the orchestrator
        // tracks delivered task_ids on the run metadata and checks them
        // individually. For the in-memory path (tests), scan the store.
        if ($this->memoryStore !== null) {
            return array_values(array_filter(
                $this->memoryStore,
                fn (array $j) => $j['parent_run_id'] === $parentRunId
                    && in_array($j['state'], ['completed', 'error'], true)
                    && ($j['delivered'] ?? false) !== true,
            ));
        }

        return [];
    }

    public function markDelivered(string $taskId): void
    {
        $job = $this->get($taskId);
        if ($job === null) {
            return;
        }
        $job['delivered'] = true;
        $this->put($taskId, $job);
    }

    public function isRunning(string $taskId): bool
    {
        $job = $this->get($taskId);

        return $job !== null && $job['state'] === 'running';
    }

    /** Use the in-memory store (for tests). */
    public function useMemoryStore(): void
    {
        $this->memoryStore = [];
    }

    private function put(string $taskId, array $data): void
    {
        if ($this->memoryStore !== null) {
            $this->memoryStore[$taskId] = $data;

            return;
        }
        Cache::put(self::CACHE_PREFIX.$taskId, $data, now()->addHours(24));
    }
}