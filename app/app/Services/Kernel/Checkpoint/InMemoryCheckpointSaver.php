<?php

namespace App\Services\Kernel\Checkpoint;

/**
 * In-process checkpoint saver for tests and ephemeral runs. Keeps an ordered
 * list of checkpoints per thread.
 */
final class InMemoryCheckpointSaver implements CheckpointSaverInterface
{
    /** @var array<string, list<Checkpoint>> */
    private array $threads = [];

    public function put(string $threadId, Checkpoint $checkpoint): void
    {
        $this->threads[$threadId][] = $checkpoint;
    }

    public function latest(string $threadId): ?Checkpoint
    {
        $list = $this->threads[$threadId] ?? [];

        return $list === [] ? null : $list[count($list) - 1];
    }

    public function get(string $threadId, string $checkpointId): ?Checkpoint
    {
        foreach ($this->threads[$threadId] ?? [] as $cp) {
            if ($cp->id === $checkpointId) {
                return $cp;
            }
        }

        return null;
    }

    public function list(string $threadId, int $limit = 50): array
    {
        $list = $this->threads[$threadId] ?? [];

        return array_slice(array_reverse($list), 0, $limit);
    }

    public function deleteThread(string $threadId): void
    {
        unset($this->threads[$threadId]);
    }
}
