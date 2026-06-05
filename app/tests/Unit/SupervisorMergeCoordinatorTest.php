<?php

namespace Tests\Unit;

use App\Models\BosskuAi\FileChange;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;
use App\Services\Orchestrator\SupervisorMergeCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupervisorMergeCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_structured_merge_report_from_children(): void
    {
        config(['bossku.supervisor_llm_synthesis' => false]);

        $parent = Run::query()->create([
            'prompt' => 'Build feature X',
            'status' => 'running',
            'run_kind' => 'supervisor',
        ]);

        $child = Run::query()->create([
            'prompt' => 'Implement API',
            'status' => 'completed',
            'run_kind' => 'child',
            'parent_run_id' => $parent->getKey(),
            'supervisor_slot' => 0,
            'final_output' => 'API done.',
        ]);

        RunWorkspace::query()->create([
            'run_id' => $child->getKey(),
            'branch_name' => 'bossku/child-api',
            'worktree_path' => '/tmp/wt-api',
            'status' => 'ready',
        ]);

        FileChange::query()->create([
            'run_id' => $child->getKey(),
            'file_path' => 'app/Http/Api.php',
            'change_type' => 'modify',
        ]);

        $merged = app(SupervisorMergeCoordinator::class)->synthesize(
            $parent,
            $parent->childRuns()->with('workspace')->get(),
        );

        $this->assertSame('completed', $merged['status']);
        $this->assertStringContainsString('Supervisor merge report', $merged['final_output']);
        $this->assertStringContainsString('bossku/child-api', $merged['final_output']);
        $this->assertSame(1, $merged['merge_report']['children_total']);
        $this->assertSame(1, $merged['merge_report']['total_files_changed']);
    }
}
