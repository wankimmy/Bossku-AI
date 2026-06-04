<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemoryServiceVectorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function skipUnlessSqlite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite driver required (run with DB_CONNECTION=sqlite or use in-memory phpunit defaults).');
        }
    }

    #[Test]
    public function cosine_similarity_scores_identical_vectors_as_one(): void
    {
        $service = new MemoryService(
            Mockery::mock(OllamaClient::class),
            Mockery::mock(LlmGateway::class),
            Mockery::mock(RuntimeSettings::class),
        );

        $vec = array_fill(0, 128, 0.25);
        $method = new \ReflectionMethod(MemoryService::class, 'cosineSimilarity');
        $method->setAccessible(true);

        $score = $method->invoke($service, $vec, $vec);
        $this->assertEqualsWithDelta(1.0, $score, 0.0001);
    }

    #[Test]
    public function sqlite_has_embedding_json_column_after_migrations(): void
    {
        $this->skipUnlessSqlite();
        $this->assertTrue(Schema::hasColumn('bossku_ai_memories', 'embedding_json'));
    }

    #[Test]
    public function sqlite_vector_search_ranks_by_cosine_similarity(): void
    {
        $this->skipUnlessSqlite();

        $ollama = Mockery::mock(OllamaClient::class);
        $settings = Mockery::mock(RuntimeSettings::class);
        $settings->shouldReceive('memoryOllamaEnabled')->andReturn(true);
        $settings->shouldReceive('maxMemoryResults')->andReturn(5);
        $settings->shouldReceive('ollamaEmbeddingPhysicalModel')->andReturn('nomic-embed-text');

        $service = new MemoryService($ollama, Mockery::mock(LlmGateway::class), $settings);

        $near = Memory::query()->create([
            'type' => 'pattern',
            'content' => 'payment webhook handler',
            'is_active' => true,
            'confidence' => 0.8,
        ]);
        $far = Memory::query()->create([
            'type' => 'pattern',
            'content' => 'unrelated gardening tips',
            'is_active' => true,
            'confidence' => 0.8,
        ]);

        $nearVec = array_fill(0, 1536, 0.0);
        $nearVec[0] = 1.0;
        $farVec = array_fill(0, 1536, 0.0);
        $farVec[1] = 1.0;

        $this->invokePersist($service, $near->id, $nearVec);
        $this->invokePersist($service, $far->id, $farVec);

        $queryVec = array_fill(0, 1536, 0.0);
        $queryVec[0] = 1.0;
        $ollama->shouldReceive('embed')->once()->andReturn($queryVec);

        $results = $service->search('payment webhook', 2);

        $this->assertGreaterThanOrEqual(1, $results->count());
        $this->assertSame($near->id, $results->first()->id);
    }

    #[Test]
    public function sqlite_persists_embedding_json_via_store(): void
    {
        $this->skipUnlessSqlite();

        $vec = array_fill(0, 1536, 0.5);
        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('embed')->once()->andReturn($vec);

        $settings = Mockery::mock(RuntimeSettings::class);
        $settings->shouldReceive('memoryOllamaEnabled')->andReturn(true);
        $settings->shouldReceive('ollamaEmbeddingPhysicalModel')->andReturn('nomic-embed-text');

        $service = new MemoryService($ollama, Mockery::mock(LlmGateway::class), $settings);
        $memory = $service->store('test memory content', 'preference');

        $raw = Memory::query()->whereKey($memory->id)->value('embedding_json');
        $this->assertNotNull($raw);
        $decoded = json_decode((string) $raw, true);
        $this->assertIsArray($decoded);
        $this->assertGreaterThanOrEqual(64, count($decoded));
    }

    /**
     * @param  list<float>  $vec
     */
    private function invokePersist(MemoryService $service, string $id, array $vec): void
    {
        $method = new \ReflectionMethod(MemoryService::class, 'persistEmbedding');
        $method->setAccessible(true);
        $method->invoke($service, $id, $vec);
    }
}
