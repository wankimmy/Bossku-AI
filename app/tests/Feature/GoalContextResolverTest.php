<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Company\GoalContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalContextResolverTest extends TestCase
{
    use RefreshDatabase;

    private GoalContextResolver $resolver;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(GoalContextResolver::class);
        $this->project = Project::query()->create([
            'name' => 'Align Co',
            'host_path' => '/tmp/align',
            'container_path' => '/tmp/align',
            'is_active' => true,
        ]);
    }

    private function goal(array $attrs): Goal
    {
        return Goal::query()->create(array_merge([
            'project_id' => $this->project->id,
            'title' => 'Goal',
            'status' => 'active',
            'priority' => 'medium',
            'progress' => 0,
        ], $attrs));
    }

    private function makeRun(array $metadata): Run
    {
        return Run::query()->create(['prompt' => 'x', 'status' => 'running', 'metadata' => $metadata]);
    }

    #[Test]
    public function explicit_goal_id_always_wins(): void
    {
        config(['bossku.align_runs_to_active_goal' => false]);
        $goal = $this->goal(['title' => 'Explicit']);
        $run = $this->makeRun(['goal_id' => $goal->id]);

        $resolved = $this->resolver->resolveForRun($run, $this->project);

        $this->assertNotNull($resolved);
        $this->assertSame($goal->id, $resolved->id);
    }

    #[Test]
    public function no_goal_when_flag_off_and_no_explicit_id(): void
    {
        config(['bossku.align_runs_to_active_goal' => false]);
        $this->goal(['title' => 'Active']);

        $this->assertNull($this->resolver->resolveForRun($this->makeRun([]), $this->project));
    }

    #[Test]
    public function falls_back_to_top_active_goal_when_enabled(): void
    {
        config(['bossku.align_runs_to_active_goal' => true]);
        $this->goal(['title' => 'Low', 'priority' => 'low']);
        $top = $this->goal(['title' => 'Critical', 'priority' => 'critical']);
        $this->goal(['title' => 'Paused', 'priority' => 'critical', 'status' => 'paused']);

        $resolved = $this->resolver->resolveForRun($this->makeRun([]), $this->project);

        $this->assertNotNull($resolved);
        $this->assertSame($top->id, $resolved->id);
    }

    #[Test]
    public function context_block_is_compact(): void
    {
        $goal = $this->goal(['title' => 'Ship', 'target_metric' => '$1M MRR', 'progress' => 40]);

        $block = $this->resolver->contextBlock($goal);

        $this->assertSame('Ship', $block['title']);
        $this->assertSame('$1M MRR', $block['target_metric']);
        $this->assertSame(40, $block['progress']);
    }
}
