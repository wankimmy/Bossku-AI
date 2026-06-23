<?php

namespace Tests\Unit;

use App\Services\BosskuAi\SystemContext\ContextKey;
use App\Services\BosskuAi\SystemContext\FileSource;
use App\Services\BosskuAi\SystemContext\ReconcileResult;
use App\Services\BosskuAi\SystemContext\SystemContext;
use App\Services\BosskuAi\SystemContext\ValueSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the System Context algebra (ported from opencode's
 * packages/core/src/system-context/). Proves: deterministic composition,
 * duplicate-key rejection, reconcile detects drift, epochs are immutable
 * within a generation, and FileSource/ValueSource compose correctly.
 */
class SystemContextTest extends TestCase
{
    #[Test]
    public function key_requires_namespaced_dotted_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContextKey('noseparator');
    }

    #[Test]
    public function namespace_prefix_is_first_segment(): void
    {
        $this->assertSame('bossku', (new ContextKey('bossku/memory/durable'))->namespace());
    }

    #[Test]
    public function duplicate_keys_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate SystemContext source key');

        SystemContext::make()
            ->add(new ValueSource(new ContextKey('bossku/x'), 'a'))
            ->add(new ValueSource(new ContextKey('bossku/x'), 'b'));
    }

    #[Test]
    public function initialize_produces_baseline_from_all_sources_in_order(): void
    {
        $ctx = SystemContext::make([
            new ValueSource(new ContextKey('bossku/a'), 'Alpha content.'),
            new ValueSource(new ContextKey('bossku/b'), 'Bravo content.'),
        ]);

        $gen = $ctx->initialize();

        $this->assertStringContainsString('Alpha content.', $gen->baseline);
        $this->assertStringContainsString('Bravo content.', $gen->baseline);
        $this->assertStringContainsString('bossku/a', $gen->baseline);
        $this->assertStringContainsString('bossku/b', $gen->baseline);
        // Sources appear in insertion order.
        $this->assertLessThan(
            strpos($gen->baseline, 'Bravo content.'),
            strpos($gen->baseline, 'Alpha content.'),
        );
        $this->assertSame(0, $gen->epoch);
    }

    #[Test]
    public function reconcile_reports_unchanged_when_values_match(): void
    {
        $ctx = SystemContext::make([
            new ValueSource(new ContextKey('bossku/x'), 'stable'),
        ]);
        $gen = $ctx->initialize();

        $result = $ctx->reconcile($gen);

        $this->assertFalse($result['changed']);
        $this->assertSame(ReconcileResult::Unchanged, $result['results']['bossku/x']);
    }

    #[Test]
    public function reconcile_detects_changed_value_source(): void
    {
        // ValueSource holds a fixed value, so a second context with a different
        // value will detect the drift.
        $ctx1 = SystemContext::make([new ValueSource(new ContextKey('bossku/x'), 'v1')]);
        $gen = $ctx1->initialize();

        $ctx2 = SystemContext::make([new ValueSource(new ContextKey('bossku/x'), 'v2')]);
        $result = $ctx2->reconcile($gen);

        $this->assertTrue($result['changed']);
        $this->assertSame(ReconcileResult::Updated, $result['results']['bossku/x']);
    }

    #[Test]
    public function reconcile_detects_removed_source(): void
    {
        $ctx1 = SystemContext::make([
            new ValueSource(new ContextKey('bossku/a'), 'a'),
            new ValueSource(new ContextKey('bossku/b'), 'b'),
        ]);
        $gen = $ctx1->initialize();

        // ctx2 drops bossku/b.
        $ctx2 = SystemContext::make([new ValueSource(new ContextKey('bossku/a'), 'a')]);
        $result = $ctx2->reconcile($gen);

        $this->assertTrue($result['changed']);
        $this->assertSame(ReconcileResult::Updated, $result['results']['bossku/b']);
    }

    #[Test]
    public function reconcile_detects_newly_added_source(): void
    {
        $ctx1 = SystemContext::make([new ValueSource(new ContextKey('bossku/a'), 'a')]);
        $gen = $ctx1->initialize();

        $ctx2 = SystemContext::make([
            new ValueSource(new ContextKey('bossku/a'), 'a'),
            new ValueSource(new ContextKey('bossku/new'), 'new'),
        ]);
        $result = $ctx2->reconcile($gen);

        $this->assertTrue($result['changed']);
        $this->assertSame(ReconcileResult::Updated, $result['results']['bossku/new']);
    }

    #[Test]
    public function replace_begins_new_epoch_with_fresh_baseline(): void
    {
        $ctx = SystemContext::make([new ValueSource(new ContextKey('bossku/x'), 'first')]);
        $gen0 = $ctx->initialize();
        $this->assertSame(0, $gen0->epoch);

        $gen1 = $ctx->replace();
        $this->assertSame(1, $gen1->epoch);
        $this->assertSame(1, $ctx->currentEpoch());
        $this->assertStringContainsString('first', $gen1->baseline);
    }

    #[Test]
    public function file_source_reads_file_contents(): void
    {
        $path = sys_get_temp_dir().'/bossku_ctx_test_'.uniqid().'.md';
        file_put_contents($path, '# Hello from file');

        try {
            $source = new FileSource(new ContextKey('bossku/file/test'), $path);
            $ctx = SystemContext::make([$source]);
            $gen = $ctx->initialize();

            $this->assertStringContainsString('# Hello from file', $gen->baseline);
            $this->assertStringContainsString('bossku/file/test', $gen->baseline);

            // Change the file; reconcile detects the drift.
            file_put_contents($path, '# Hello from file (updated)');
            $result = $ctx->reconcile($gen);
            $this->assertTrue($result['changed']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function file_source_returns_empty_for_missing_file(): void
    {
        $source = new FileSource(new ContextKey('bossku/file/missing'), '/nonexistent/path.md');
        $ctx = SystemContext::make([$source]);
        $gen = $ctx->initialize();

        // Missing file -> empty string -> empty baseline text.
        $this->assertSame('', $gen->baseline);
    }

    #[Test]
    public function empty_source_contributes_no_text(): void
    {
        $ctx = SystemContext::make([
            new ValueSource(new ContextKey('bossku/empty'), ''),
            new ValueSource(new ContextKey('bossku/full'), 'content'),
        ]);
        $gen = $ctx->initialize();

        // Empty source contributes '' so baseline is just the full source.
        $this->assertStringContainsString('content', $gen->baseline);
        $this->assertStringNotContainsString('bossku/empty', $gen->baseline);
    }
}