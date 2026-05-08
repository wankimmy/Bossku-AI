<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Memory;
use App\Services\Llm\OllamaClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemoryService
{
    public function __construct(
        protected OllamaClient $ollama,
        protected LlmGateway $llmGateway,
        protected RuntimeSettings $settings
    ) {}

    protected function databaseDriver(): string
    {
        return (string) DB::connection()->getDriverName();
    }

    protected function memoryOllamaEnabled(): bool
    {
        return filter_var(config('bossku.memory_ollama_enabled', true), FILTER_VALIDATE_BOOL);
    }

    /** @param array<string,mixed> $metadata */
    public function store(
        string $content,
        string $type,
        array $metadata = [],
        ?string $humanSummary = null,
        array $tags = [],
        ?string $source = null
    ): Memory {
        $embedding = null;
        if ($this->memoryOllamaEnabled()) {
            try {
                $physical = $this->settings->ollamaEmbeddingPhysicalModel();
                $vec = $this->ollama->embed($content, $physical);
                $embedding = $this->normalizeEmbedding($vec);
            } catch (\Throwable) {
                $embedding = null;
            }
        }

        /** @var Memory $row */
        $row = Memory::query()->create([
            'type' => $type,
            'content' => $content,
            'human_summary' => $humanSummary,
            'metadata' => $metadata,
            'tags' => $tags,
            'source' => $source,
            'is_active' => true,
            'confidence' => null,
        ]);

        if ($embedding && count($embedding) >= 64 && $this->databaseDriver() === 'pgsql') {
            $this->persistEmbedding($row->getKey(), $embedding);
        }

        return $row->fresh();
    }

    /**
     * @return Collection<int, Memory>
     */
    public function search(string $query, ?int $topK = null): Collection
    {
        $limit = $topK ?? $this->settings->maxMemoryResults();

        if (
            $this->memoryOllamaEnabled()
            && $this->databaseDriver() === 'pgsql'
        ) {
            try {
                $physical = $this->settings->ollamaEmbeddingPhysicalModel();
                $vec = $this->normalizeEmbedding($this->ollama->embed($query, $physical));
                if (count($vec) >= 64) {
                    return $this->vectorSearch($vec, $limit);
                }
            } catch (\Throwable) {
                //
            }
        }

        return $this->textSearchFallback($query, $limit);
    }

    /** @return Collection<int, Memory> */
    protected function textSearchFallback(string $query, int $limit): Collection
    {
        $op = $this->databaseDriver() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Memory::query()
            ->where('is_active', true)
            ->where(function ($q) use ($query, $op) {
                $q->where('content', $op, '%'.$query.'%')
                    ->orWhere('human_summary', $op, '%'.$query.'%');
            })
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function humanize(Memory $memory): Memory
    {
        if (! $this->memoryOllamaEnabled()) {
            $memory->human_summary = $memory->human_summary ?: Str::limit(strip_tags($memory->content), 200);

            return tap($memory)->save();
        }

        try {
            $model = $this->settings->memoryHumanizeLogicalModel();
            $out = $this->llmGateway->chat($model, [
                ['role' => 'system', 'content' => 'Rewrite the following durable memory note as one short neutral sentence for humans.'],
                ['role' => 'user', 'content' => $memory->content],
            ], 0.2);

            $memory->human_summary = trim($out['text']);
            $memory->save();
        } catch (\Throwable) {
            //
        }

        return $memory;
    }

    /** @param array<string,mixed> $data */
    public function updateMemory(string $id, array $data): Memory
    {
        /** @var Memory $m */
        $m = Memory::query()->findOrFail($id);
        $m->fill(collect($data)->only([
            'type', 'content', 'human_summary', 'metadata', 'tags', 'confidence', 'source', 'is_active',
        ])->all());
        $m->save();

        if (($data['content'] ?? null) !== null && $this->memoryOllamaEnabled()) {
            try {
                $physical = $this->settings->ollamaEmbeddingPhysicalModel();
                $vec = $this->normalizeEmbedding($this->ollama->embed($m->content, $physical));
                $this->persistEmbedding($m->getKey(), $vec);
            } catch (\Throwable) {
                //
            }
        }

        return $m->fresh();
    }

    public function disableMemory(string $id): Memory
    {
        /** @var Memory $m */
        $m = Memory::query()->findOrFail($id);
        $m->update(['is_active' => false]);

        return $m->fresh();
    }

    public function deleteMemory(string $id): bool
    {
        return (bool) Memory::query()->whereKey($id)->delete();
    }

    /**
     * @param  list<float>  $vec
     * @return list<float>
     */
    protected function normalizeEmbedding(array $vec): array
    {
        $vec = array_values(array_map(static fn ($v): float => (float) $v, $vec));
        $vec = array_slice($vec, 0, 1536);
        while (count($vec) < 1536) {
            $vec[] = 0.0;
        }

        return $vec;
    }

    /** @param list<float> $vec */
    protected function persistEmbedding(string $id, array $vec): void
    {
        if ($this->databaseDriver() !== 'pgsql') {
            return;
        }
        $slice = array_slice($vec, 0, 1536);
        $literal = '['.implode(',', array_map(fn (float $f) => sprintf('%.8f', $f), $slice)).']';
        DB::update(
            'UPDATE bossku_ai_memories SET embedding = ?::vector WHERE id = ?::uuid',
            [$literal, $id]
        );
    }

    /** @param list<float> $vec */
    protected function vectorSearch(array $vec, int $limit): Collection
    {
        $slice = array_slice($vec, 0, 1536);
        $literal = '['.implode(',', array_map(fn (float $f) => sprintf('%.8f', $f), $slice)).']';
        /** @var list<object{id:string, similarity:float}> $rows */
        $rows = DB::select(
            'SELECT id, (1 - (embedding <=> ?::vector))::float AS similarity FROM bossku_ai_memories WHERE is_active = TRUE AND embedding IS NOT NULL ORDER BY embedding <=> ?::vector ASC LIMIT '.$limit,
            [$literal, $literal]
        );
        if ($rows === []) {
            return collect();
        }
        /** @var list<string> $ids */
        $ids = array_map(fn ($r) => (string) $r->id, $rows);
        $scores = [];
        foreach ($rows as $r) {
            $scores[(string) $r->id] = $r->similarity;
        }

        /** @var Collection<string, Memory> */
        return Memory::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortByDesc(fn ($m) => $scores[$m->getKey()] ?? 0)
            ->values();
    }
}
