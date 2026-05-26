<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\ExecutorApprovalService;
use App\Services\Orchestrator\ExecutorEvidenceSupport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorCodeReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function collect_code_review_instructions_from_rejected_approvals(): void
    {
        $repo = sys_get_temp_dir().'/bkreview_'.uniqid();
        \Illuminate\Support\Facades\File::ensureDirectoryExists($repo);
        config(['bossku.repo_root' => $repo]);

        Project::query()->create([
            'name' => 'Review test',
            'host_path' => $repo,
            'container_path' => $repo,
            'is_active' => true,
        ]);

        $run = Run::query()->create(['prompt' => 'test', 'status' => 'running']);
        $gates = app(ApprovalGateService::class);
        $service = app(ExecutorApprovalService::class);

        $approval = $gates->createApproval(
            $run->id,
            null,
            'file_write',
            'Modify Foo.php',
            'low',
            [
                'path' => 'app/Foo.php',
                'before' => "<?php\n",
                'after' => "<?php\n// bad\n",
                'change_type' => 'modified',
            ],
        );
        $gates->decide($approval->id, 'rejected', 'user', 'Use a Form Request for validation');

        $collected = $service->collectCodeReviewInstructions($run->id);

        $this->assertStringContainsString('Form Request', $collected['instructions']);
        $this->assertCount(1, $collected['items']);
        $this->assertTrue($service->hasRejectedFileWritesWithReviewNotes($run->id));

        $feedback = $service->formatDecisionFeedback($run->id);
        $this->assertStringContainsString('Code review instructions', $feedback);

        \Illuminate\Support\Facades\File::deleteDirectory($repo);
    }

    #[Test]
    public function user_code_review_payload_includes_instructions(): void
    {
        $payload = ExecutorEvidenceSupport::userCodeReviewPayloadForRevision(
            ['files_changed' => []],
            'run-1',
            'Fix validation',
            [['path' => 'app/Foo.php', 'change_type' => 'modified', 'before' => 'a', 'user_note' => 'Fix validation']],
            'User feedback',
        );

        $this->assertSame('user_code_review', $payload['revision_type']);
        $this->assertSame('Fix validation', $payload['code_review_instructions']);
        $this->assertNotEmpty($payload['required_actions']);
    }

    #[Test]
    public function mark_approval_request_changes_sets_metadata(): void
    {
        $run = Run::query()->create(['prompt' => 'test', 'status' => 'running']);
        $gates = app(ApprovalGateService::class);
        $service = app(ExecutorApprovalService::class);

        $approval = $gates->createApproval(
            $run->id,
            null,
            'file_write',
            'Modify x',
            'low',
            ['path' => 'x.txt', 'before' => 'a', 'after' => 'b', 'change_type' => 'modified'],
        );

        $service->markApprovalRequestChanges($approval, true);
        $approval = $approval->fresh() ?? $approval;

        $this->assertTrue(($approval->metadata['request_changes'] ?? false) === true);
    }
}
