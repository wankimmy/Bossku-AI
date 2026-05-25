<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Memory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeImportApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function url_import_stores_article_text_as_active_knowledge_memory(): void
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><title>Useful Article</title><meta name="description" content="Article summary"></head><body><article><p>Bossku should learn durable article facts.</p></article></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $response = $this->postJson('/api/knowledge/urls', [
            'urls' => ['https://example.com/article'],
            'tags' => ['ai', 'article'],
            'note' => 'Research dump',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('failed', 0);

        $memory = Memory::query()->where('type', 'knowledge')->firstOrFail();
        $this->assertSame('url', $memory->source);
        $this->assertTrue($memory->is_active);
        $this->assertStringContainsString('Bossku should learn durable article facts.', $memory->content);
        $this->assertSame('Useful Article', $memory->human_summary);
        $this->assertContains('knowledge', $memory->tags);
        $this->assertContains('ai', $memory->tags);
        $this->assertSame('https://example.com/article', $memory->metadata['url']);
        $this->assertSame('Research dump', $memory->metadata['note']);
    }

    #[Test]
    public function url_import_skips_duplicate_content_hashes(): void
    {
        Http::fake([
            'https://example.com/article' => Http::response(
                '<html><head><title>Duplicate Article</title></head><body><p>Same durable fact.</p></body></html>'
            ),
        ]);

        $payload = ['urls' => ['https://example.com/article']];

        $this->postJson('/api/knowledge/urls', $payload)->assertStatus(200);
        $second = $this->postJson('/api/knowledge/urls', $payload);

        $second->assertStatus(200)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('failed', 0);

        $this->assertSame(1, Memory::query()->where('type', 'knowledge')->count());
    }

    #[Test]
    public function url_import_rejects_private_and_local_urls_without_storing_memory(): void
    {
        $response = $this->postJson('/api/knowledge/urls', [
            'urls' => ['http://127.0.0.1/admin'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('failed', 1);

        $this->assertSame(0, Memory::query()->where('type', 'knowledge')->count());
    }

    #[Test]
    public function youtube_import_stores_transcript_when_available(): void
    {
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(['title' => 'Demo Video', 'author_name' => 'Bossku'], 200),
            'https://video.google.com/timedtext*' => Http::response('<transcript><text start="0" dur="2">Transcript fact one</text><text start="2" dur="2">Transcript fact two</text></transcript>', 200),
        ]);

        $response = $this->postJson('/api/knowledge/urls', [
            'urls' => ['https://www.youtube.com/watch?v=abc123XYZ09'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('items.0.status', 'imported');

        $memory = Memory::query()->where('type', 'knowledge')->firstOrFail();
        $this->assertStringContainsString('Transcript fact one', $memory->content);
        $this->assertSame('youtube_transcript', $memory->metadata['extractor']);
    }

    #[Test]
    public function youtube_import_stores_partial_metadata_when_transcript_is_missing(): void
    {
        Http::fake([
            'https://www.youtube.com/oembed*' => Http::response(['title' => 'No Transcript Video', 'author_name' => 'Bossku'], 200),
            'https://video.google.com/timedtext*' => Http::response('', 200),
        ]);

        $response = $this->postJson('/api/knowledge/urls', [
            'urls' => ['https://youtu.be/abc123XYZ09'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('items.0.status', 'partial');

        $memory = Memory::query()->where('type', 'knowledge')->firstOrFail();
        $this->assertStringContainsString('No Transcript Video', $memory->content);
        $this->assertSame('partial', $memory->metadata['status']);
    }

    #[Test]
    public function codex_import_reads_configured_local_memory_files(): void
    {
        $dir = sys_get_temp_dir().'/bossku_codex_memory_'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/MEMORY.md', "# Memory\n\nCodex durable fact for BosskuAI.");
        config(['bossku.knowledge_import_paths.codex' => [$dir]]);
        config(['bossku.repo_root' => $dir.'/missing-repo-root']);

        $response = $this->postJson('/api/knowledge/import-memory', ['source' => 'codex']);

        $response->assertStatus(200)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('failed', 0);

        $this->assertDatabaseHas('bossku_ai_memories', [
            'type' => 'knowledge',
            'source' => 'codex',
            'is_active' => true,
        ]);
        $this->assertStringContainsString('Codex durable fact', Memory::query()->firstOrFail()->content);

        File::deleteDirectory($dir);
    }

    #[Test]
    public function claude_import_reads_jsonl_and_redacts_secret_like_values(): void
    {
        $dir = sys_get_temp_dir().'/bossku_claude_memory_'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/session.jsonl', json_encode([
            'type' => 'summary',
            'summary' => 'Claude learned api_key=super-secret-value and a durable architecture rule.',
        ])."\n");
        config(['bossku.knowledge_import_paths.claude' => [$dir]]);
        config(['bossku.repo_root' => $dir.'/missing-repo-root']);

        $response = $this->postJson('/api/knowledge/import-memory', ['source' => 'claude']);

        $response->assertStatus(200)
            ->assertJsonPath('created', 1)
            ->assertJsonPath('failed', 0);

        $memory = Memory::query()->where('source', 'claude')->firstOrFail();
        $this->assertStringContainsString('[REDACTED]', $memory->content);
        $this->assertStringNotContainsString('super-secret-value', $memory->content);

        File::deleteDirectory($dir);
    }
}
