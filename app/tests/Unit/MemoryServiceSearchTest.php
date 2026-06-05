<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\MemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemoryServiceSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_text_fallback_matches_when_memory_ollama_disabled(): void
    {
        config(['bossku.memory_ollama_enabled' => false]);

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
    public function search_falls_back_to_text_when_ollama_embed_fails(): void
    {
        config(['bossku.memory_ollama_enabled' => true]);

        Http::fake([
            '*' => Http::response(['error' => 'simulated embed failure'], 503),
        ]);

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

        /** @var MemoryService $svc */
        $svc = app(MemoryService::class);

        $hits = $svc->search('token_abc_second', 10);
        $this->assertCount(1, $hits);
        $this->assertStringContainsString('token_abc_second', $hits->first()->content);
    }

    #[Test]
    public function store_survives_ollama_embed_http_failure(): void
    {
        config(['bossku.memory_ollama_enabled' => true]);

        Http::fake([
            '*' => Http::response(['error' => 'simulated embed failure'], 503),
        ]);

        /** @var MemoryService $svc */
        $svc = app(MemoryService::class);

        $memory = $svc->store(
            content: 'token_embed_fail_unique',
            type: 'fact',
            metadata: [],
            tags: [],
            source: 'test'
        );

        $this->assertSame('token_embed_fail_unique', $memory->content);

        $hits = $svc->search('token_embed_fail_unique', 10);
        $this->assertCount(1, $hits);
    }
}
