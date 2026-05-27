<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeLearnUrlApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function learn_url_youtube_stores_chunked_memory(): void
    {
        $transcript = str_repeat('Transcript fact one. ', 20);
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(['title' => 'Demo Video'], 200),
            'https://video.google.com/timedtext*' => Http::response(
                '<transcript><text start="0" dur="2">'.$transcript.'</text></transcript>',
                200,
            ),
        ]);

        $response = $this->postJson('/api/knowledge/learn-url', [
            'url' => 'https://www.youtube.com/watch?v=abc123XYZ09',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('type', 'youtube')
            ->assertJsonPath('title', 'Demo Video');

        $this->assertGreaterThan(0, (int) $response->json('indexed'));
        $this->assertDatabaseHas('bossku_ai_memories', [
            'type' => 'knowledge_chunk',
            'source' => 'https://www.youtube.com/watch?v=abc123XYZ09',
        ]);
    }

    #[Test]
    public function learn_url_youtube_without_captions_returns_422(): void
    {
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(['title' => 'No Captions'], 200),
            'https://video.google.com/timedtext*' => Http::response('', 200),
            'https://www.youtube.com/watch*' => Http::response('<html></html>', 200),
        ]);

        $response = $this->postJson('/api/knowledge/learn-url', [
            'url' => 'https://www.youtube.com/watch?v=abc123XYZ09',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'youtube_no_captions')
            ->assertJsonStructure(['error']);

        $this->assertStringContainsString('caption', strtolower((string) $response->json('error')));
        $this->assertSame(0, Memory::query()->where('type', 'knowledge_chunk')->count());
    }

    #[Test]
    public function learn_url_web_article_stores_chunks(): void
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><title>Useful Article</title></head><body><p>'.str_repeat('Bossku learns from articles. ', 30).'</p></body></html>',
                200,
            ),
        ]);

        $response = $this->postJson('/api/knowledge/learn-url', [
            'url' => 'https://example.com/article',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('type', 'web')
            ->assertJsonPath('title', 'Useful Article');

        $this->assertGreaterThan(0, Memory::query()->where('type', 'knowledge_chunk')->count());
    }

    #[Test]
    public function learn_url_requires_valid_url(): void
    {
        $response = $this->postJson('/api/knowledge/learn-url', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    #[Test]
    public function learn_url_rejects_private_urls(): void
    {
        $response = $this->postJson('/api/knowledge/learn-url', [
            'url' => 'http://127.0.0.1/secret',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'URL is not allowed.');
    }
}
