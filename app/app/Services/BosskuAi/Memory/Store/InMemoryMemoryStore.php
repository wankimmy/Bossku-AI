<?php

namespace App\Services\BosskuAi\Memory\Store;

/**
 * An in-memory MemoryStoreInterface implementation for testing and for
 * environments without a database. Also the reference implementation that
 * the conformance trait runs against. Ported from langgraph's InMemorySaver
 * pattern (the simplest correct adapter).
 */
final class InMemoryMemoryStore implements MemoryStoreInterface
{
    /** @var array<string, array{id: string, content: string, human_summary: ?string, type: string, tags: list<string>, confidence: float, metadata: array<string, mixed>, is_active: bool, usage_count: int, last_used_at: ?string}> */
    private array $memories = [];

    /** @var array<string, list<array{run_id: string, score: ?float}>> */
    private array $usage = [];

    public function ingest(array $record): string
    {
        $id = \Illuminate\Support\Str::uuid()->toString();
        $this->memories[$id] = [
            'id' => $id,
            'content' => (string) ($record['content'] ?? ''),
            'human_summary' => $record['human_summary'] ?? null,
            'type' => (string) ($record['type'] ?? 'durable'),
            'tags' => is_array($record['tags'] ?? null) ? $record['tags'] : [],
            'confidence' => (float) ($record['confidence'] ?? 0.65),
            'metadata' => is_array($record['metadata'] ?? null) ? $record['metadata'] : [],
            'is_active' => true,
            'usage_count' => 0,
            'last_used_at' => null,
        ];

        return $id;
    }

    public function search(string $query, int $limit = 10, array $contextTags = []): array
    {
        $q = mb_strtolower($query);
        $scored = [];
        foreach ($this->memories as $mem) {
            if (! $mem['is_active']) {
                continue;
            }
            $haystack = mb_strtolower($mem['content'].' '.($mem['human_summary'] ?? ''));
            $score = str_contains($haystack, $q) ? 1.0 : 0.0;
            // Boost by tag overlap.
            if ($contextTags !== []) {
                $overlap = count(array_intersect(
                    array_map('strtolower', $contextTags),
                    array_map('strtolower', $mem['tags']),
                ));
                $score += $overlap * 0.1;
            }
            if ($score > 0) {
                $scored[] = ['score' => $score] + $mem;
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    public function browse(array $filters = []): array
    {
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $type = $filters['type'] ?? null;
        $active = $filters['is_active'] ?? true;

        $rows = array_values(array_filter($this->memories, function (array $m) use ($type, $active): bool {
            if ($type !== null && $m['type'] !== $type) {
                return false;
            }
            if ($active === true && ! $m['is_active']) {
                return false;
            }

            return true;
        }));

        return array_slice($rows, $offset, $limit);
    }

    public function get(string $id): ?array
    {
        $mem = $this->memories[$id] ?? null;
        if ($mem === null) {
            return null;
        }

        return [
            'id' => $mem['id'],
            'content' => $mem['content'],
            'human_summary' => $mem['human_summary'],
            'type' => $mem['type'],
            'tags' => $mem['tags'],
            'confidence' => $mem['confidence'],
            'metadata' => $mem['metadata'],
        ];
    }

    public function forget(string $id): bool
    {
        if (! isset($this->memories[$id])) {
            return false;
        }
        $this->memories[$id]['is_active'] = false;

        return true;
    }

    public function recordUsage(string $memoryId, string $runId, ?float $similarityScore = null): void
    {
        if (! isset($this->memories[$memoryId])) {
            return;
        }
        $this->memories[$memoryId]['usage_count']++;
        $this->memories[$memoryId]['last_used_at'] = now()->toIso8601String();
        $this->usage[$memoryId][] = ['run_id' => $runId, 'score' => $similarityScore];
    }

    /** @return array<string, list<array{run_id: string, score: ?float}>> */
    public function usageLog(): array
    {
        return $this->usage;
    }

    public function count(): int
    {
        return count($this->memories);
    }
}