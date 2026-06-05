<?php

namespace Tests\Feature;

use App\Models\BosskuAi\CodeChunk;
use App\Services\BosskuAi\CodebaseIndexService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CodebaseIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?string $root = null;

    protected function tearDown(): void
    {
        if ($this->root !== null && is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }
        parent::tearDown();
    }

    /** @param array<string, string> $files */
    private function makeProject(array $files): string
    {
        $this->root = sys_get_temp_dir().'/bossku_codeidx_'.uniqid();
        foreach ($files as $rel => $content) {
            $full = $this->root.'/'.$rel;
            File::ensureDirectoryExists(dirname($full));
            File::put($full, $content);
        }

        return $this->root;
    }

    private function serviceWithEmbeddingsOff(): CodebaseIndexService
    {
        $settings = Mockery::mock(RuntimeSettings::class);
        $settings->shouldReceive('memoryOllamaEnabled')->andReturnFalse();
        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldNotReceive('embed');

        /** @var OllamaClient $ollama */
        /** @var RuntimeSettings $settings */
        return new CodebaseIndexService($ollama, $settings);
    }

    #[Test]
    public function it_indexes_chunks_then_skips_unchanged_and_prunes_removed_files(): void
    {
        $svc = $this->serviceWithEmbeddingsOff();
        $root = $this->makeProject([
            'src/Payment.php' => "<?php\n// process refunds\n".str_repeat("\$x = 1;\n", 80),
            'README.md' => "# Project\nDocs here.",
            'node_modules/dep/index.js' => "module.exports = 1;", // must be skipped (skip_dirs)
        ]);

        $stats = $svc->indexDirectory($root, 'proj-1');

        $this->assertSame(2, $stats['files']);
        $this->assertGreaterThan(2, $stats['chunks']); // Payment.php spans multiple chunks
        $this->assertSame(0, $stats['embedded']);
        $this->assertDatabaseHas('bossku_ai_code_chunks', ['project_id' => 'proj-1', 'path' => 'src/Payment.php']);
        $this->assertDatabaseMissing('bossku_ai_code_chunks', ['path' => 'node_modules/dep/index.js']);

        // Re-index unchanged → everything skipped, nothing re-chunked.
        $again = $svc->indexDirectory($root, 'proj-1');
        $this->assertSame(0, $again['files']);
        $this->assertSame(2, $again['skipped']);

        // Remove a file and change another → pruned + re-chunked.
        File::delete($root.'/README.md');
        File::put($root.'/src/Payment.php', "<?php\n// changed\necho 'hi';");
        $svc->indexDirectory($root, 'proj-1');

        $this->assertDatabaseMissing('bossku_ai_code_chunks', ['path' => 'README.md']);
        $this->assertSame(1, CodeChunk::query()->where('path', 'src/Payment.php')->count());
    }

    #[Test]
    public function it_retrieves_by_keyword_when_embeddings_disabled(): void
    {
        $svc = $this->serviceWithEmbeddingsOff();
        $root = $this->makeProject([
            'app/Billing.php' => "<?php\nfunction processPaymentRefund() { return true; }",
            'app/Mailer.php' => "<?php\nfunction sendNewsletterEmail() { return true; }",
        ]);
        $svc->indexDirectory($root, 'proj-2');

        $hits = $svc->retrieve('process a payment refund', 3, 'proj-2');

        $this->assertNotEmpty($hits);
        $this->assertSame('app/Billing.php', $hits[0]['path']);
    }

    #[Test]
    public function it_ranks_by_cosine_on_sqlite_when_embeddings_enabled(): void
    {
        $settings = Mockery::mock(RuntimeSettings::class);
        $settings->shouldReceive('memoryOllamaEnabled')->andReturnTrue();
        $settings->shouldReceive('ollamaEmbeddingPhysicalModel')->andReturn('nomic-embed-text');

        // Deterministic 1536-dim embeddings: "payment" content/query points one way, everything else another.
        $vecFor = static function (string $text): array {
            $v = array_fill(0, 1536, 0.0);
            $v[str_contains(strtolower($text), 'payment') ? 0 : 1] = 1.0;

            return $v;
        };
        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('embed')->andReturnUsing(fn (string $text) => $vecFor($text));

        /** @var OllamaClient $ollama */
        /** @var RuntimeSettings $settings */
        $svc = new CodebaseIndexService($ollama, $settings);

        $root = $this->makeProject([
            'app/Billing.php' => "<?php\n// payment refund logic\nfunction refund() {}",
            'app/Mailer.php' => "<?php\n// newsletter logic\nfunction send() {}",
        ]);
        $stats = $svc->indexDirectory($root, 'proj-3');
        $this->assertGreaterThan(0, $stats['embedded']);

        $hits = $svc->retrieve('handle a payment', 5, 'proj-3');

        $this->assertNotEmpty($hits);
        $this->assertSame('app/Billing.php', $hits[0]['path']);
        $this->assertGreaterThan(0.9, $hits[0]['similarity']);
    }
}
