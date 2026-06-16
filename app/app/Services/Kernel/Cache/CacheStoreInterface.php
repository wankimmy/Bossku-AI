<?php

namespace App\Services\Kernel\Cache;

/**
 * Backing store for node-result caching. Implementations: InMemory (tests) and
 * Database (production).
 */
interface CacheStoreInterface
{
    /**
     * @return array<string, mixed>|null cached node output, or null on miss/expiry
     */
    public function get(string $key): ?array;

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value, ?int $ttlSeconds = null): void;

    public function forget(string $key): void;
}
