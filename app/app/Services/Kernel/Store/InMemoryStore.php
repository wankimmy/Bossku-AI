<?php

namespace App\Services\Kernel\Store;

/**
 * Reference in-process implementation of the long-term StoreInterface. Provides
 * namespaced KV with naive substring search — enough for tests and local runs.
 * The production implementation wraps the existing pgvector memory (Phase 4
 * integration).
 */
final class InMemoryStore implements StoreInterface
{
    /** @var array<string, array<string, array{value: array<string,mixed>, expires: ?float}>> */
    private array $data = [];

    public function put(array $namespace, string $key, array $value, ?int $ttlSeconds = null): void
    {
        $ns = $this->nsKey($namespace);
        $this->data[$ns][$key] = [
            'value' => $value,
            'expires' => $ttlSeconds !== null ? microtime(true) + $ttlSeconds : null,
        ];
    }

    public function get(array $namespace, string $key): ?array
    {
        $entry = $this->data[$this->nsKey($namespace)][$key] ?? null;
        if ($entry === null) {
            return null;
        }
        if ($entry['expires'] !== null && microtime(true) > $entry['expires']) {
            unset($this->data[$this->nsKey($namespace)][$key]);

            return null;
        }

        return $entry['value'];
    }

    public function search(array $namespace, ?string $query = null, int $limit = 10): array
    {
        $ns = $this->nsKey($namespace);
        $results = [];
        foreach ($this->data[$ns] ?? [] as $key => $entry) {
            if ($entry['expires'] !== null && microtime(true) > $entry['expires']) {
                continue;
            }
            if ($query !== null && ! str_contains(strtolower((string) json_encode($entry['value'])), strtolower($query))) {
                continue;
            }
            $results[] = ['namespace' => $namespace, 'key' => $key, 'value' => $entry['value']];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    public function delete(array $namespace, string $key): void
    {
        unset($this->data[$this->nsKey($namespace)][$key]);
    }

    public function listNamespaces(array $prefix = []): array
    {
        $prefixKey = $this->nsKey($prefix);
        $out = [];
        foreach (array_keys($this->data) as $ns) {
            if ($prefix === [] || str_starts_with($ns, $prefixKey)) {
                $out[] = explode("\x1f", $ns);
            }
        }

        return $out;
    }

    /** @param list<string> $namespace */
    private function nsKey(array $namespace): string
    {
        return implode("\x1f", $namespace);
    }
}
