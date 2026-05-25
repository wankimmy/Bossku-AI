<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Memory;
use App\Services\BosskuAi\KnowledgeCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeCaptureServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function import_urls_accepts_google_search_pages_as_partial_knowledge_when_fetch_fails(): void
    {
        Http::fake([
            'https://www.google.com/search*' => Http::response('blocked', 403),
        ]);

        /** @var KnowledgeCaptureService $service */
        $service = app(KnowledgeCaptureService::class);

        $result = $service->importUrls(['https://www.google.com/search?q=bossku+ai'], ['research'], null);

        $this->assertSame(1, $result['created']);
        $this->assertSame('partial', $result['items'][0]['status']);

        $memory = Memory::query()->where('type', 'knowledge')->firstOrFail();
        $this->assertStringContainsString('https://www.google.com/search?q=bossku+ai', $memory->content);
        $this->assertSame('url_partial', $memory->metadata['extractor']);
    }

    #[Test]
    public function local_memory_import_skips_env_like_files_and_dedupes_content(): void
    {
        $dir = sys_get_temp_dir().'/bossku_memory_import_'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put($dir.'/raw_memories.md', "Reusable memory note.\n");
        File::put($dir.'/copy.md', "Reusable memory note.\n");
        File::put($dir.'/.env', "PASSWORD=do-not-store\n");
        config(['bossku.knowledge_import_paths.codex' => [$dir]]);
        config(['bossku.repo_root' => $dir.'/missing-repo-root']);

        /** @var KnowledgeCaptureService $service */
        $service = app(KnowledgeCaptureService::class);

        $result = $service->importLocalMemory('codex');

        $this->assertSame(1, $result['created']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame(1, Memory::query()->where('type', 'knowledge')->count());
        $this->assertStringNotContainsString('do-not-store', (string) Memory::query()->firstOrFail()->content);

        File::deleteDirectory($dir);
    }
}
