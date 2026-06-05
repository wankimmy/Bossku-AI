<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\MemoryWorktreeScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MemoryWorktreeScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_for_run_excludes_other_worktree_scoped_memories(): void
    {
        config([
            'bossku.memory_worktree_scoping' => true,
            'bossku.memory_ollama_enabled' => false,
        ]);

        $repo = sys_get_temp_dir().'/bkmem_'.uniqid();
        File::ensureDirectoryExists($repo.'/wt-a');
        File::ensureDirectoryExists($repo.'/wt-b');
        $pathA = realpath($repo.'/wt-a') ?: $repo.'/wt-a';
        $pathB = realpath($repo.'/wt-b') ?: $repo.'/wt-b';

        Memory::query()->create([
            'type' => 'pattern',
            'content' => 'Global routing note about tests',
            'human_summary' => 'Global',
            'is_active' => true,
            'confidence' => 0.9,
            'metadata' => [],
        ]);

        Memory::query()->create([
            'type' => 'pattern',
            'content' => 'Worktree A routing note about tests',
            'human_summary' => 'Scoped A',
            'is_active' => true,
            'confidence' => 0.9,
            'metadata' => [MemoryWorktreeScope::META_KEY => $pathA],
        ]);

        Memory::query()->create([
            'type' => 'pattern',
            'content' => 'Worktree B routing note about tests',
            'human_summary' => 'Scoped B',
            'is_active' => true,
            'confidence' => 0.9,
            'metadata' => [MemoryWorktreeScope::META_KEY => $pathB],
        ]);

        $run = Run::query()->create(['prompt' => 't', 'status' => 'running']);
        RunWorkspace::query()->create([
            'run_id' => $run->getKey(),
            'branch_name' => 'bossku/a',
            'worktree_path' => $pathA,
            'status' => 'ready',
        ]);

        $results = app(MemoryService::class)->searchForRun($run, 'routing note', 10);
        $summaries = $results->map(fn (Memory $m) => (string) $m->human_summary)->all();

        $this->assertContains('Global', $summaries);
        $this->assertContains('Scoped A', $summaries);
        $this->assertNotContains('Scoped B', $summaries);

        File::deleteDirectory($repo);
    }
}
