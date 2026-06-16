<?php

namespace App\Services\Kernel\Store;

/**
 * Cross-thread long-term memory store (LangGraph's BaseStore). Namespaced
 * key/value with optional semantic search and TTL. In Phase 4 this is
 * implemented over the existing pgvector memory so every node gets a uniform
 * ctx->store; the interface is defined now as the seam.
 *
 * @phpstan-type Namespace list<string>
 */
interface StoreInterface
{
    /** @param list<string> $namespace */
    public function put(array $namespace, string $key, array $value, ?int $ttlSeconds = null): void;

    /**
     * @param  list<string>  $namespace
     * @return array<string, mixed>|null
     */
    public function get(array $namespace, string $key): ?array;

    /**
     * @param  list<string>  $namespace
     * @return list<array{namespace: list<string>, key: string, value: array<string, mixed>, score?: float}>
     */
    public function search(array $namespace, ?string $query = null, int $limit = 10): array;

    /** @param list<string> $namespace */
    public function delete(array $namespace, string $key): void;

    /**
     * @param  list<string>  $prefix
     * @return list<list<string>>
     */
    public function listNamespaces(array $prefix = []): array;
}
