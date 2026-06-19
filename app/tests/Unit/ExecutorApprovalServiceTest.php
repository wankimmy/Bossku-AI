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
            'bossku.require_user_approval_for_commands' => false,
            'bossku.auto_apply_file_writes' => true,
            'bossku.auto_execute_project_commands' => true,
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
    public function it_creates_pending_file_approval_without_applying(): void
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

        $this->assertCount(1, $fileOut['pending_approval_ids']);
        $this->assertSame("original\n", File::get($this->repo.'/keep.txt'));

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $runId,
            'status' => 'pending',
            'operation_type' => 'file_write',
        ]);
        $this->assertDatabaseMissing('bossku_ai_approvals', [
            'run_id' => $runId,
            'operation_type' => 'terminal_command',
        ]);
    }

    #[Test]
    public function propose_commands_still_available_when_command_approval_enabled(): void
    {
        config(['bossku.require_user_approval_for_commands' => true]);
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;
        $service = app(ExecutorApprovalService::class);
        $cmdPending = $service->proposeCommands($runId, ['git status']);

        $this->assertCount(1, $cmdPending);
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
    public function it_creates_pending_approval_for_nested_new_file(): void
    {
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;
        $service = app(ExecutorApprovalService::class);

        $out = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'docs/PRODUCT_SPEC.md',
                'change_type' => 'created',
                'after' => "# Product Spec\n\nNested write proof.\n",
            ]],
        ]);

        $this->assertCount(1, $out['pending_approval_ids']);
        $this->assertArrayNotHasKey('approval_error', $out['execResult']['files_changed'][0]);
        $this->assertSame('docs/PRODUCT_SPEC.md', $out['execResult']['files_changed'][0]['path']);
        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $runId,
            'operation_type' => 'file_write',
            'status' => 'pending',
        ]);
        $this->assertFileDoesNotExist($this->repo.'/docs/PRODUCT_SPEC.md');
    }

    #[Test]
    public function approved_nested_file_write_creates_parent_directory(): void
    {
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;
        $service = app(ExecutorApprovalService::class);

        $out = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'docs/PRODUCT_SPEC.md',
                'change_type' => 'created',
                'after' => "# Product Spec\n\nNested write proof.\n",
            ]],
        ]);

        $approval = Approval::query()->findOrFail($out['pending_approval_ids'][0]);
        $approval->update(['status' => 'approved', 'decided_by' => 'test', 'decided_at' => now()]);

        $service->applyApproved($approval);

        $this->assertFileExists($this->repo.'/docs/PRODUCT_SPEC.md');
        $this->assertSame(
            "# Product Spec\n\nNested write proof.\n",
            File::get($this->repo.'/docs/PRODUCT_SPEC.md'),
        );
    }

    #[Test]
    public function long_documentation_with_task_language_is_not_treated_as_placeholder(): void
    {
        $runId = Run::query()->create(['prompt' => 'test', 'status' => 'running'])->id;
        $service = app(ExecutorApprovalService::class);
        $content = "# Implementation Status\n\n"
            ."This document records completed, pending, and todo work without being placeholder content.\n\n"
            .str_repeat("- Feature area documented with concrete acceptance criteria and implementation notes.\n", 12);

        $out = $service->proposeFileChanges($runId, [
            'files_changed' => [[
                'path' => 'docs/IMPLEMENTATION_STATUS.md',
                'change_type' => 'created',
                'after' => $content,
            ]],
        ]);

        $this->assertCount(1, $out['pending_approval_ids']);
        $approval = Approval::query()->findOrFail($out['pending_approval_ids'][0]);
        $approval->update(['status' => 'approved', 'decided_by' => 'test', 'decided_at' => now()]);

        $service->applyApproved($approval);

        $this->assertSame($content, File::get($this->repo.'/docs/IMPLEMENTATION_STATUS.md'));
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
