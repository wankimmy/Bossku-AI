<?php

namespace Tests\Feature\Kernel;

use App\Services\Kernel\Cache\DatabaseCacheStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseCacheStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_and_reads_node_output(): void
    {
        $store = new DatabaseCacheStore;
        $store->put('node:abc', ['plan' => 'x', 'n' => 2]);

        $this->assertSame(['plan' => 'x', 'n' => 2], $store->get('node:abc'));
        $this->assertDatabaseHas('bossku_ai_node_cache', ['cache_key' => 'node:abc']);
    }

    #[Test]
    public function it_misses_on_unknown_and_after_forget(): void
    {
        $store = new DatabaseCacheStore;
        $this->assertNull($store->get('missing'));

        $store->put('k', ['a' => 1]);
        $store->forget('k');
        $this->assertNull($store->get('k'));
    }

    #[Test]
    public function it_expires_entries_past_ttl(): void
    {
        $store = new DatabaseCacheStore;
        $store->put('k', ['a' => 1], ttlSeconds: -1); // already expired

        $this->assertNull($store->get('k'));
        $this->assertDatabaseMissing('bossku_ai_node_cache', ['cache_key' => 'k']);
    }
}
