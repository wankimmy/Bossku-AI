<?php

namespace App\Services\Kernel\Cache;

use Illuminate\Support\Facades\DB;

/**
 * Durable node-result cache backed by bossku_ai_node_cache. Survives across runs
 * so identical agent steps (same node + same state) are not recomputed.
 */
final class DatabaseCacheStore implements CacheStoreInterface
{
    public function get(string $key): ?array
    {
        $row = DB::table('bossku_ai_node_cache')->where('cache_key', $key)->first();
        if ($row === null) {
            return null;
        }
        if ($row->expires_at !== null && now()->greaterThan($row->expires_at)) {
            $this->forget($key);

            return null;
        }

        $decoded = json_decode((string) $row->value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, array $value, ?int $ttlSeconds = null): void
    {
        DB::table('bossku_ai_node_cache')->updateOrInsert(
            ['cache_key' => $key],
            [
                'value' => (string) json_encode($value),
                'expires_at' => $ttlSeconds !== null ? now()->addSeconds($ttlSeconds) : null,
                'created_at' => now(),
            ],
        );
    }

    public function forget(string $key): void
    {
        DB::table('bossku_ai_node_cache')->where('cache_key', $key)->delete();
    }
}
