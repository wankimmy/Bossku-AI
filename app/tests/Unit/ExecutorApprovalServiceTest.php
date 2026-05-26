<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Governance\ExecutorApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkapprove_'.uniqid();
        File::ensureDirectoryExists($this->repo);
        config([
            'bossku.repo_root' => $this->repo,
            'bossku.require_user_approval_before_apply' => true,
            'bossku.auto_apply_file_writes' => true,
        ]);

        Project::query()->create([
            'name' => 'Approval test',
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
    public function it_creates_pending_file_and_command_approvals_without_applying(): void
    {
        File::put($this->repo.'/keep.txt', "original\n");
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;

        $service = app(ExecutorApprovalService::class);
        $fileOut = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'keep.txt',
                'change_type' => 'modified',
                'after' => "changed\n",
            ]],
        ]);
        $cmdPending = $service->proposeCommands($runId, ['git status']);

        $this->assertCount(1, $fileOut['pending_approval_ids']);
        $this->assertCount(1, $cmdPending);
        $this->assertSame("original\n", File::get($this->repo.'/keep.txt'));

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $runId,
            'status' => 'pending',
            'operation_type' => 'file_write',
        ]);
        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $runId,
            'status' => 'pending',
            'operation_type' => 'terminal_command',
        ]);
    }

    #[Test]
    public function apply_approved_writes_file_to_disk(): void
    {
        File::put($this->repo.'/apply.txt', "before\n");
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;
        $service = app(ExecutorApprovalService::class);
        $out = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'apply.txt',
                'change_type' => 'modified',
                'after' => "after\n",
            ]],
        ]);

        $approval = Approval::query()->findOrFail($out['pending_approval_ids'][0]);
        $approval->update(['status' => 'approved', 'decided_by' => 'test', 'decided_at' => now()]);

        $service->applyApproved($approval);

        $this->assertSame("after\n", File::get($this->repo.'/apply.txt'));
    }

    #[Test]
    public function it_skips_placeholder_file_proposals_without_creating_approval(): void
    {
        $before = implode("\n", array_map(fn ($i) => "<?php // line {$i}", range(1, 25)));
        File::put($this->repo.'/ReceiptController.php', $before);
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;

        $service = app(ExecutorApprovalService::class);
        $out = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'ReceiptController.php',
                'change_type' => 'modified',
                'after' => 'Will be determined after reading the file',
            ]],
        ]);

        $this->assertSame([], $out['pending_approval_ids']);
        $this->assertDatabaseMissing('bossku_ai_approvals', [
            'run_id' => $runId,
            'operation_type' => 'file_write',
        ]);
        $this->assertTrue($out['execResult']['files_changed'][0]['approval_skipped'] ?? false);
        $this->assertSame($before, File::get($this->repo.'/ReceiptController.php'));
    }
}
