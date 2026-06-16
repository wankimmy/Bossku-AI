<?php

namespace App\Services\Kernel\Checkpoint;

/**
 * Persists per-superstep checkpoints for a thread (thread id = run id). Backs
 * resume-from-crash, time-travel, and fork. Implementations: InMemory (tests)
 * and Database (production).
 */
interface CheckpointSaverInterface
{
    public function put(string $threadId, Checkpoint $checkpoint): void;

    /** Most recent checkpoint for the thread, or null. */
    public function latest(string $threadId): ?Checkpoint;

    public function get(string $threadId, string $checkpointId): ?Checkpoint;

    /**
     * Checkpoint history, newest first.
     *
     * @return list<Checkpoint>
     */
    public function list(string $threadId, int $limit = 50): array;

    public function deleteThread(string $threadId): void;
}
