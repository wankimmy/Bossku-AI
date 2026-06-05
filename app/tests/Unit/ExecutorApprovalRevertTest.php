<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\ExecutorApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorApprovalRevertTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkrevert_'.uniqid();
        File::ensureDirectoryExists($this->repo);
        config([
            'bossku.repo_root' => $this->repo,
            'bossku.require_user_approval_before_apply' => true,
        ]);

        Project::query()->create([
            'name' => 'Revert test',
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
    public function it_restores_before_content_when_rejected_file_was_modified_on_disk(): void
    {
        File::put($this->repo.'/dirty.txt', "wrong\n");
        $run = Run::query()->create(['prompt' => 'test', 'status' => 'running']);
        $gates = app(ApprovalGateService::class);
        $service = app(ExecutorApprovalService::class);

        $approval = $gates->createApproval(
            $run->id,
            null,
            'file_write',
            'Modify dirty.txt',
            'low',
            [
                'path' => 'dirty.txt',
                'before' => "original\n",
                'after' => "proposed\n",
                'change_type' => 'modified',
            ],
        );
        $gates->decide($approval->id, 'rejected', 'user', 'No thanks');

        $result = $service->revertRejectedFileWrite($approval->fresh() ?? $approval);

        $this->assertTrue($result['reverted']);
        $this->assertSame("original\n", File::get($this->repo.'/dirty.txt'));
    }

    #[Test]
    public function format_decision_feedback_instructs_executor_to_revert_rejected_files(): void
    {
        $run = Run::query()->create(['prompt' => 'test', 'status' => 'running']);
        $gates = app(ApprovalGateService::class);
        $service = app(ExecutorApprovalService::class);

        $approval = $gates->createApproval(
            $run->id,
            null,
            'file_write',
            'Modify app/Foo.php',
            'low',
            [
                'path' => 'app/Foo.php',
                'before' => "<?php\n",
                'after' => "<?php\n// bad\n",
                'change_type' => 'modified',
            ],
        );
        $gates->decide($approval->id, 'rejected', 'user');

        $feedback = $service->formatDecisionFeedback($run->id);

        $this->assertStringContainsString('REJECTED file changes', $feedback);
        $this->assertStringContainsString('executor MUST revert', $feedback);
        $this->assertStringContainsString('app/Foo.php', $feedback);
    }
}
