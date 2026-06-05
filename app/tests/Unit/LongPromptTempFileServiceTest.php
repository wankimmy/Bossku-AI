<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Services\Runs\LongPromptTempFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LongPromptTempFileServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bossku_long_prompt_'.uniqid();
        File::ensureDirectoryExists($this->root);

        Project::query()->create([
            'name' => 'Long Prompt Project',
            'host_path' => 'C:\\Users\\Safwan Hakim\\Documents\\Safwan\\LongPrompt',
            'container_path' => $this->root,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function materializes_oversized_prompt_into_chunked_repo_temp_files(): void
    {
        $prompt = 'Please inspect this log carefully.'."\n\n"
            .str_repeat('A', LongPromptTempFileService::INLINE_LIMIT + 1)
            ."\n\nThe final marker is LONG_PROMPT_END.";

        $prepared = app(LongPromptTempFileService::class)->prepare($prompt);

        $this->assertTrue($prepared['materialized']);
        $this->assertLessThan(LongPromptTempFileService::INLINE_LIMIT, strlen($prepared['prompt']));
        $this->assertStringContainsString('Long prompt attached', $prepared['prompt']);
        $this->assertStringContainsString('tmp/bossku-prompts/', $prepared['prompt']);
        $this->assertStringContainsString('chunks/chunk-001.md', $prepared['prompt']);

        $metadata = $prepared['metadata'];
        $this->assertSame(strlen($prompt), $metadata['original_length']);
        $this->assertSame(hash('sha256', $prompt), $metadata['sha256']);
        $this->assertGreaterThan(1, $metadata['chunk_count']);
        $this->assertSame('pending', $metadata['cleanup_status']);
        $this->assertFileExists($this->root.'/'.$metadata['prompt_path']);
        $this->assertFileExists($this->root.'/'.$metadata['manifest_path']);
        $this->assertFileExists($this->root.'/'.$metadata['chunk_paths'][0]);
    }

    #[Test]
    public function cleanup_removes_materialized_prompt_directory(): void
    {
        $prepared = app(LongPromptTempFileService::class)->prepare(str_repeat('B', LongPromptTempFileService::INLINE_LIMIT + 1));
        $metadata = $prepared['metadata'];

        $this->assertDirectoryExists($this->root.'/'.$metadata['relative_dir']);

        $cleaned = app(LongPromptTempFileService::class)->cleanup($metadata);

        $this->assertSame('deleted', $cleaned['cleanup_status']);
        $this->assertDirectoryDoesNotExist($this->root.'/'.$metadata['relative_dir']);
    }
}
