<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\RunSupervisorService;
use App\Services\Workspace\WorktreeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunSupervisorServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_spawns_parent_and_child_runs(): void
    {
        Queue::fake();
        config(['bossku.worktree_enabled' => false]);

        $supervisor = app(RunSupervisorService::class);
        $result = $supervisor->spawnParallelChildren('Supervisor task', [
            ['prompt' => 'Child A'],
            ['prompt' => 'Child B'],
        ]);

        $this->assertArrayHasKey('parent_run_id', $result);
        $this->assertCount(2, $result['child_run_ids']);

        $parent = Run::query()->find($result['parent_run_id']);
        $this->assertNotNull($parent);
        $this->assertSame('supervisor', $parent->run_kind);
        $this->assertSame(2, $parent->childRuns()->count());
    }

    #[Test]
    public function it_finalizes_parent_with_merge_coordinator(): void
    {
        config(['bossku.worktree_enabled' => false, 'bossku.supervisor_llm_synthesis' => false]);

        $parent = Run::query()->create([
            'prompt' => 'Supervisor',
            'status' => 'running',
            'run_kind' => 'supervisor',
        ]);
        Run::query()->create([
            'prompt' => 'Child',
            'status' => 'completed',
            'run_kind' => 'child',
            'parent_run_id' => $parent->getKey(),
            'supervisor_slot' => 0,
            'final_output' => 'Done.',
        ]);

        app(RunSupervisorService::class)->maybeFinalizeParent($parent->refresh());

        $parent->refresh();
        $this->assertSame('completed', $parent->status);
        $this->assertStringContainsString('Supervisor merge report', (string) $parent->final_output);
        $this->assertArrayHasKey('supervisor_merge', is_array($parent->metadata) ? $parent->metadata : []);
    }

    #[Test]
    public function finalize_parent_is_idempotent(): void
    {
        config(['bossku.worktree_enabled' => false, 'bossku.supervisor_llm_synthesis' => false]);

        $parent = Run::query()->create([
            'prompt' => 'Supervisor',
            'status' => 'running',
            'run_kind' => 'supervisor',
        ]);
        Run::query()->create([
            'prompt' => 'Child',
            'status' => 'completed',
            'run_kind' => 'child',
            'parent_run_id' => $parent->getKey(),
            'supervisor_slot' => 0,
            'final_output' => 'Done.',
        ]);

        $supervisor = app(RunSupervisorService::class);
        $supervisor->maybeFinalizeParent($parent->refresh());
        $first = $parent->refresh()->final_output;
        $supervisor->maybeFinalizeParent($parent->refresh());
        $this->assertSame($first, $parent->refresh()->final_output);
    }
}
