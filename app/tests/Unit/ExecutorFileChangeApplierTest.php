<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\ExecutorFileChangeApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorFileChangeApplierTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkauto_'.uniqid();
        File::ensureDirectoryExists($this->repo.'/src');
        File::put($this->repo.'/src/existing.txt', "before\n");
        config([
            'bossku.repo_root' => $this->repo,
            'bossku.auto_apply_file_writes' => true,
            'bossku.require_user_approval_before_apply' => false,
        ]);

        Project::query()->create([
            'name' => 'Auto apply test',
            'host_path' => $this->repo,
            'container_path' => $this->repo,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->repo)) {
            File::deleteDirectory($this->repo);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_auto_applies_executor_files_changed_with_after_content(): void
    {
        $runId = Run::query()->create(['prompt' => 'auto apply test', 'status' => 'running'])->id;
        $applier = app(ExecutorFileChangeApplier::class);

        $result = $applier->applyFromExecutorResult($runId, [
            'files_changed' => [[
                'path' => 'src/new-pricing.txt',
                'change_type' => 'created',
                'summary' => 'Add pricing copy',
                'after' => "tier one\n",
            ]],
        ]);

        $this->assertSame(['src/new-pricing.txt'], $result['applied']);
        $this->assertSame([], $result['errors']);
        $this->assertFileExists($this->repo.'/src/new-pricing.txt');
        $this->assertSame("tier one\n", File::get($this->repo.'/src/new-pricing.txt'));
        $this->assertNotEmpty($result['execResult']['files_changed'][0]['diff'] ?? null);
        $this->assertStringContainsString('tier one', (string) ($result['execResult']['files_changed'][0]['after'] ?? ''));

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $runId,
            'operation_type' => 'file_write',
            'status' => 'auto_approved',
        ]);
    }

    #[Test]
    public function it_skips_files_without_writable_content(): void
    {
        $runId = Run::query()->create(['prompt' => 'skip test', 'status' => 'running'])->id;
        $applier = app(ExecutorFileChangeApplier::class);

        $result = $applier->applyFromExecutorResult($runId, [
            'files_changed' => [[
                'path' => 'src/ghost.txt',
                'change_type' => 'modified',
                'summary' => 'Claimed only',
            ]],
        ]);

        $this->assertSame([], $result['applied']);
        $this->assertStringContainsString('no after', $result['skipped'][0]);
        $this->assertFalse(Approval::query()->where('run_id', $runId)->exists());
    }
}
