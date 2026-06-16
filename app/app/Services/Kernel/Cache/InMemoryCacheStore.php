<?php

namespace App\Services\Kernel\Cache;

/**
 * In-process node-result cache for tests and single-run reuse.
 */
final class InMemoryCacheStore implements CacheStoreInterface
{
    /** @var array<string, array{value: array<string, mixed>, expires: ?float}> */
    private array $entries = [];

    public function get(string $key): ?array
    {
        $entry = $this->entries[$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['expires'] !== null && microtime(true) > $entry['expires']) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry['value'];
    }

    public function put(string $key, array $value, ?int $ttlSeconds = null): void
    {
        $this->entries[$key] = [
            'value' => $value,
            'expires' => $ttlSeconds !== null ? microtime(true) + $ttlSeconds : null,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }
}
