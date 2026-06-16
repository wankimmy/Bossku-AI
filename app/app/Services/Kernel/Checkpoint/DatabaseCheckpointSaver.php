<?php

namespace App\Services\Kernel\Checkpoint;

use App\Models\BosskuAi\Checkpoint as CheckpointModel;

/**
 * Durable checkpoint saver backed by the bossku_ai_checkpoints table. thread_id
 * is the run id, so checkpoints hang off the existing Run lifecycle and a
 * crashed run can be reloaded and resumed.
 */
final class DatabaseCheckpointSaver implements CheckpointSaverInterface
{
    public function put(string $threadId, Checkpoint $checkpoint): void
    {
        CheckpointModel::query()->create([
            'id' => $checkpoint->id,
            'thread_id' => $threadId,
            'parent_id' => $checkpoint->parentId,
            'channel_values' => $checkpoint->channelValues,
            'next' => $checkpoint->next,
            'step' => $checkpoint->step,
            'source' => $checkpoint->source,
            'metadata' => $checkpoint->metadata,
        ]);
    }

    public function latest(string $threadId): ?Checkpoint
    {
        $row = CheckpointModel::query()
            ->where('thread_id', $threadId)
            ->orderByDesc('step')
            ->orderByDesc('created_at')
            ->first();

        return $row === null ? null : $this->toValue($row);
    }

    public function get(string $threadId, string $checkpointId): ?Checkpoint
    {
        $row = CheckpointModel::query()
            ->where('thread_id', $threadId)
            ->where('id', $checkpointId)
            ->first();

        return $row === null ? null : $this->toValue($row);
    }

    public function list(string $threadId, int $limit = 50): array
    {
        return CheckpointModel::query()
            ->where('thread_id', $threadId)
            ->orderByDesc('step')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (CheckpointModel $row): Checkpoint => $this->toValue($row))
            ->all();
    }

    public function deleteThread(string $threadId): void
    {
        CheckpointModel::query()->where('thread_id', $threadId)->delete();
    }

    private function toValue(CheckpointModel $row): Checkpoint
    {
        return new Checkpoint(
            id: (string) $row->id,
            parentId: $row->parent_id,
            channelValues: is_array($row->channel_values) ? $row->channel_values : [],
            next: is_array($row->next) ? array_values($row->next) : [],
            step: (int) $row->step,
            source: (string) $row->source,
            metadata: is_array($row->metadata) ? $row->metadata : [],
        );
    }
}
