<?php

namespace App\Services\BosskuAi\Memory\Store;

/**
 * The provider-adapter contract for durable memory storage and retrieval.
 * Ported from paperclip's two-layer memory contract: Bossku-AI owns the
 * control plane (provenance, scope, confidence, conflict, usage reporting);
 * providers own extraction/embedding/ranking.
 *
 * Implementations:
 * - DatabaseMemoryStore (the current Eloquent+SQLite/Postgres default)
 * - future: VectorDbMemoryStore, HostedMemoryStore (mem0-style)
 *
 * The conformance trait (MemoryStoreConformance) validates any implementation
 * against this contract so swapping backends is safe.
 */
interface MemoryStoreInterface
{
    /**
     * Ingest a memory record. Returns the persisted record's identifier.
     *
     * @param  array{content: string, type: string, human_summary?: ?string, tags?: list<string>, source?: ?string, confidence?: float, metadata?: array<string, mixed>}  $record
     * @return string the persisted memory id
     */
    public function ingest(array $record): string;

    /**
     * Search for memories matching a query. Returns a ranked list.
     *
     * @param  string  $query  the search text
     * @param  int  $limit  max results
     * @param  list<string>  $contextTags  tags to boost
     * @return list<array{id: string, content: string, human_summary: ?string, type: string, tags: list<string>, confidence: float, score: float}>
     */
    public function search(string $query, int $limit = 10, array $contextTags = []): array;

    /**
     * Browse memories with optional filters (type, active, pagination).
     *
     * @param  array{type?: string, is_active?: bool, limit?: int, offset?: int}  $filters
     * @return list<array{id: string, content: string, human_summary: ?string, type: string, tags: list<string>, confidence: float}>
     */
    public function browse(array $filters = []): array;

    /**
     * Get a single memory by id.
     *
     * @return ?array{id: string, content: string, human_summary: ?string, type: string, tags: list<string>, confidence: float, metadata: array<string, mixed>}
     */
    public function get(string $id): ?array;

    /**
     * Forget (deactivate or delete) a memory. Returns true if a record was affected.
     */
    public function forget(string $id): bool;

    /**
     * Report usage: record that a memory was used in a run.
     *
     * @param  string  $memoryId
     * @param  string  $runId
     * @param  ?float  $similarityScore
     */
    public function recordUsage(string $memoryId, string $runId, ?float $similarityScore = null): void;
}