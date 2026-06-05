<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Governance\ExecutorApprovalService;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PartialFileApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkpartial_'.uniqid();
        File::ensureDirectoryExists($this->repo);
        config([
            'bossku.repo_root' => $this->repo,
            'bossku.require_user_approval_before_apply' => true,
            'bossku.api_auth_enabled' => false,
        ]);

        Project::query()->create([
            'name' => 'Partial approval test',
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
    public function approving_one_of_three_file_writes_applies_only_that_file(): void
    {
        File::put($this->repo.'/one.txt', "one-before\n");
        File::put($this->repo.'/two.txt', "two-before\n");
        File::put($this->repo.'/three.txt', "three-before\n");

        $run = Run::query()->create(['prompt' => 'test', 'status' => 'running']);
        $service = app(ExecutorApprovalService::class);
        $out = $service->proposeFileChanges($run->id, [
            'files_changed' => [
                ['path' => 'one.txt', 'change_type' => 'modified', 'after' => "one-after\n"],
                ['path' => 'two.txt', 'change_type' => 'modified', 'after' => "two-after\n"],
                ['path' => 'three.txt', 'change_type' => 'modified', 'after' => "three-after\n"],
            ],
        ]);

        $this->assertCount(3, $out['pending_approval_ids']);
        $firstId = $out['pending_approval_ids'][0];

        $response = $this->postJson("/api/approvals/{$firstId}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('run_has_pending', true);

        $this->assertSame("one-after\n", File::get($this->repo.'/one.txt'));
        $this->assertSame("two-before\n", File::get($this->repo.'/two.txt'));
        $this->assertSame("three-before\n", File::get($this->repo.'/three.txt'));

        $this->assertTrue(app(ExecutorApprovalService::class)->hasPendingForRun($run->id));
    }

    #[Test]
    public function continue_after_approvals_throws_while_pending_file_writes_remain(): void
    {
        File::put($this->repo.'/a.txt', "a-before\n");
        File::put($this->repo.'/b.txt', "b-before\n");

        $run = Run::factory()->create([
            'status' => 'awaiting_input',
            'metadata' => [
                'checkpoint' => [
                    'phase' => 'awaiting_approvals',
                    'stage' => 'executor_approvals',
                    'approval_ids' => [],
                    'pipeline' => ['user_prompt' => 'test', 'exec_result' => []],
                ],
            ],
        ]);

        $service = app(ExecutorApprovalService::class);
        $out = $service->proposeFileChanges($run->id, [
            'files_changed' => [
                ['path' => 'a.txt', 'change_type' => 'modified', 'after' => "a-after\n"],
                ['path' => 'b.txt', 'change_type' => 'modified', 'after' => "b-after\n"],
            ],
        ]);

        $this->postJson("/api/approvals/{$out['pending_approval_ids'][0]}/approve")
            ->assertOk()
            ->assertJsonPath('run_has_pending', true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/pending approvals \(1 remaining\)/');

        app(OrchestratorService::class)->continueAfterApprovals($run->id);
    }
}
