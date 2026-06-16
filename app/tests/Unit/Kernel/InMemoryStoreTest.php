<?php

namespace Tests\Unit\Kernel;

use App\Services\Kernel\Store\InMemoryStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InMemoryStoreTest extends TestCase
{
    #[Test]
    public function it_puts_gets_and_deletes_namespaced_values(): void
    {
        $store = new InMemoryStore;
        $ns = ['memories', 'user-1'];

        $store->put($ns, 'k1', ['text' => 'likes dark mode']);
        $this->assertSame(['text' => 'likes dark mode'], $store->get($ns, 'k1'));

        $store->delete($ns, 'k1');
        $this->assertNull($store->get($ns, 'k1'));
    }

    #[Test]
    public function search_filters_by_substring_and_namespace(): void
    {
        $store = new InMemoryStore;
        $ns = ['memories', 'user-1'];
        $store->put($ns, 'a', ['text' => 'prefers TypeScript']);
        $store->put($ns, 'b', ['text' => 'prefers PHP']);
        $store->put(['memories', 'user-2'], 'c', ['text' => 'prefers PHP']);

        $hits = $store->search($ns, 'typescript');
        $this->assertCount(1, $hits);
        $this->assertSame('a', $hits[0]['key']);
    }

    #[Test]
    public function list_namespaces_returns_known_namespaces(): void
    {
        $store = new InMemoryStore;
        $store->put(['memories', 'user-1'], 'a', ['x' => 1]);
        $store->put(['cache', 'graph'], 'b', ['x' => 2]);

        $this->assertContains(['memories', 'user-1'], $store->listNamespaces(['memories']));
        $this->assertNotContains(['cache', 'graph'], $store->listNamespaces(['memories']));
    }
}
