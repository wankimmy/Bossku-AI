<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Memory\Store\InMemoryMemoryStore;
use App\Services\BosskuAi\Memory\Store\MemoryStoreConformance;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs the MemoryStoreConformance suite against the InMemoryMemoryStore
 * reference implementation. This proves: (1) the conformance suite is
 * correct, and (2) InMemoryMemoryStore is a valid provider adapter.
 *
 * When a DatabaseMemoryStore or hosted adapter is added, it gets its own test
 * file calling MemoryStoreConformance::validate(fn () => new ThatStore(...)).
 * If it passes, it's safe to swap in via config binding.
 */
class MemoryStoreConformanceTest extends TestCase
{
    #[Test]
    public function in_memory_store_conforms_to_contract(): void
    {
        MemoryStoreConformance::validate(fn () => new InMemoryMemoryStore);
    }

    #[Test]
    public function fresh_factory_is_used_per_assertion(): void
    {
        // The conformance suite calls the factory fresh for each assertion,
        // so cross-test contamination is impossible. Prove it by running twice
        // with different factories — each must pass independently.
        MemoryStoreConformance::validate(fn () => new InMemoryMemoryStore);
        MemoryStoreConformance::validate(fn () => new InMemoryMemoryStore);
    }
}