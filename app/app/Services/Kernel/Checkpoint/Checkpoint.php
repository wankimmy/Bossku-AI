<?php

namespace App\Services\Kernel\Checkpoint;

use Illuminate\Support\Str;

/**
 * An immutable snapshot of a run at one superstep boundary: the full channel
 * state plus the frontier (next nodes) needed to resume. This is what makes
 * execution durable — a crashed run reloads its latest checkpoint and continues
 * from `next`.
 */
final class Checkpoint
{
    /**
     * @param  array<string, mixed>  $channelValues  RunState::checkpoint() output
     * @param  list<string>  $next  frontier node names to run next
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $parentId,
        public readonly array $channelValues,
        public readonly array $next,
        public readonly int $step,
        public readonly string $source,
        public readonly array $metadata = [],
    ) {}

    public static function newId(): string
    {
        return (string) Str::uuid();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'channel_values' => $this->channelValues,
            'next' => $this->next,
            'step' => $this->step,
            'source' => $this->source,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            parentId: $row['parent_id'] ?? null,
            channelValues: is_array($row['channel_values'] ?? null) ? $row['channel_values'] : [],
            next: is_array($row['next'] ?? null) ? array_values($row['next']) : [],
            step: (int) ($row['step'] ?? 0),
            source: (string) ($row['source'] ?? 'loop'),
            metadata: is_array($row['metadata'] ?? null) ? $row['metadata'] : [],
        );
    }
}
