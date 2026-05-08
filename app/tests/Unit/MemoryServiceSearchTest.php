<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\MemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemoryServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_text_fallback_matches_without_schema_connection_facade_bug(): void
    {
        config(['services.openai.key' => null]);

        Memory::query()->create([
            'type' => 'fact',
            'content' => 'Notes about refactor uniqueToken_xyz',
            'human_summary' => null,
            'metadata' => [],
            'tags' => [],
            'source' => 'test',
            'is_active' => true,
            'confidence' => null,
        ]);

        /** @var MemoryService $svc */
        $svc = app(MemoryService::class);

        $hits = $svc->search('uniqueToken_xyz', 10);
        $this->assertCount(1, $hits);
        $this->assertStringContainsString('uniqueToken_xyz', $hits->first()->content);
    }

    #[Test]
    public function search_skips_vector_path_when_database_is_not_pgsql_and_still_matches_text(): void
    {
        config(['services.openai.key' => 'sk-test-nonempty']);

        Memory::query()->create([
            'type' => 'fact',
            'content' => 'Second row token_abc_second',
            'human_summary' => null,
            'metadata' => [],
            'tags' => [],
            'source' => 'test',
            'is_active' => true,
            'confidence' => null,
        ]);

        $this->assertNotSame('pgsql', \Illuminate\Support\Facades\DB::connection()->getDriverName());

        /** @var MemoryService $svc */
        $svc = app(MemoryService::class);

        $hits = $svc->search('token_abc_second', 10);
        $this->assertCount(1, $hits);
        $this->assertStringContainsString('token_abc_second', $hits->first()->content);
    }
}
