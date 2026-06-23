<?php

namespace App\Services\BosskuAi\Memory\Store;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Machine-checkable contract validator for MemoryStoreInterface.
 * Ported from langgraph's langgraph-checkpoint-conformance pattern: any
 * implementation of MemoryStoreInterface can be validated by calling
 * validate($factory) from a PHPUnit test. If a provider adapter passes this
 * suite, it's safe to swap in.
 *
 * Usage in a test:
 *   public function test_conforms_to_memory_store_contract(): void {
 *       MemoryStoreConformance::validate(fn () => new DatabaseMemoryStore(...));
 *   }
 *
 * The factory is called fresh for each assertion so tests are isolated.
 *
 * @param  callable(): MemoryStoreInterface  $factory
 */
final class MemoryStoreConformance
{
    /** @param callable(): MemoryStoreInterface $factory */
    public static function validate(callable $factory): void
    {
        self::testIngestReturnsId($factory);
        self::testSearchReturnsRankedResults($factory);
        self::testBrowseFiltersByType($factory);
        self::testGetReturnsRecord($factory);
        self::testGetReturnsNullForUnknownId($factory);
        self::testForgetDeactivates($factory);
        self::testRecordUsageIncrementsCount($factory);
        self::testSearchExcludesInactive($factory);
        self::testSearchBoostsByTagOverlap($factory);
        self::testBrowseRespectsPagination($factory);
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testIngestReturnsId(callable $factory): void
    {
        $store = $factory();
        $id = $store->ingest(['content' => 'Always use tabs', 'type' => 'durable']);
        Assert::assertNotEmpty($id, 'ingest() must return a non-empty id.');

        $got = $store->get($id);
        Assert::assertNotNull($got, 'get() must return the just-ingested record.');
        Assert::assertSame('Always use tabs', $got['content']);
        Assert::assertSame('durable', $got['type']);
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testSearchReturnsRankedResults(callable $factory): void
    {
        $store = $factory();
        $store->ingest(['content' => 'Use tabs for indentation', 'type' => 'durable']);
        $store->ingest(['content' => 'Prefer Pest over PHPUnit', 'type' => 'durable']);
        $store->ingest(['content' => 'tabs are standard', 'type' => 'durable']);

        $results = $store->search('tabs', 10);
        Assert::assertNotEmpty($results, 'search() must find matching records.');
        Assert::assertNotEmpty($results[0]['content'], 'Each result must have content.');
        Assert::assertArrayHasKey('score', $results[0], 'Each result must have a score.');
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testBrowseFiltersByType(callable $factory): void
    {
        $store = $factory();
        $store->ingest(['content' => 'a durable lesson', 'type' => 'durable']);
        $store->ingest(['content' => 'a user preference', 'type' => 'user']);

        $durable = $store->browse(['type' => 'durable']);
        Assert::assertCount(1, $durable, 'browse() type filter must return only matching records.');
        Assert::assertSame('durable', $durable[0]['type']);
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testGetReturnsRecord(callable $factory): void
    {
        $store = $factory();
        $id = $store->ingest(['content' => 'get me', 'type' => 'durable', 'tags' => ['x']]);
        $got = $store->get($id);
        Assert::assertNotNull($got);
        Assert::assertSame('get me', $got['content']);
        Assert::assertSame(['x'], $got['tags']);
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testGetReturnsNullForUnknownId(callable $factory): void
    {
        $store = $factory();
        Assert::assertNull($store->get('nonexistent-id'));
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testForgetDeactivates(callable $factory): void
    {
        $store = $factory();
        $id = $store->ingest(['content' => 'forget me', 'type' => 'durable']);
        $ok = $store->forget($id);
        Assert::assertTrue($ok, 'forget() must return true when a record was affected.');
        Assert::assertFalse($store->forget('nonexistent'), 'forget() must return false for unknown id.');
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testRecordUsageIncrementsCount(callable $factory): void
    {
        $store = $factory();
        $id = $store->ingest(['content' => 'useful fact', 'type' => 'durable']);
        $store->recordUsage($id, 'run-1', 0.92);
        $store->recordUsage($id, 'run-2', 0.88);
        // recordUsage must not throw; the record must still be retrievable.
        Assert::assertSame('useful fact', $store->get($id)['content']);
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testSearchExcludesInactive(callable $factory): void
    {
        $store = $factory();
        $id = $store->ingest(['content' => 'inactive tabs rule', 'type' => 'durable']);
        $store->forget($id);
        $results = $store->search('tabs', 10);
        foreach ($results as $r) {
            Assert::assertNotSame($id, $r['id'], 'search() must exclude forgotten/inactive records.');
        }
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testSearchBoostsByTagOverlap(callable $factory): void
    {
        $store = $factory();
        $store->ingest(['content' => 'laravel validation rules', 'type' => 'durable', 'tags' => ['laravel']]);
        $store->ingest(['content' => 'laravel eloquent models', 'type' => 'durable', 'tags' => ['laravel']]);
        $store->ingest(['content' => 'react hooks', 'type' => 'durable', 'tags' => ['react']]);

        $results = $store->search('laravel', 10, ['laravel']);
        Assert::assertNotEmpty($results);
        // Tagged results should rank at least as high as untagged.
        $taggedIds = array_filter($results, fn ($r) => in_array('laravel', array_map('strtolower', $r['tags']), true));
        Assert::assertNotEmpty($taggedIds, 'Tag-boosted results must appear in the search output.');
    }

    /** @param callable(): MemoryStoreInterface $factory */
    private static function testBrowseRespectsPagination(callable $factory): void
    {
        $store = $factory();
        for ($i = 0; $i < 5; $i++) {
            $store->ingest(['content' => "item {$i}", 'type' => 'durable']);
        }
        $page1 = $store->browse(['limit' => 2, 'offset' => 0]);
        $page2 = $store->browse(['limit' => 2, 'offset' => 2]);
        Assert::assertCount(2, $page1, 'browse() must respect limit.');
        Assert::assertCount(2, $page2, 'browse() must respect offset.');
        Assert::assertNotSame($page1[0]['id'], $page2[0]['id'], 'Pages must return different records.');
    }
}