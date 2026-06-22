<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\WorkIssue;
use App\Services\Company\GoalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoalServiceTest extends TestCase
{
    use RefreshDatabase;

    private GoalService $goals;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->goals = app(GoalService::class);
        $this->project = Project::query()->create([
            'name' => 'Goals Co',
            'host_path' => '/tmp/goals',
            'container_path' => '/tmp/goals',
            'is_active' => true,
        ]);
    }

    private function issue(Goal $goal, string $status): WorkIssue
    {
        return WorkIssue::query()->create([
            'project_id' => $this->project->id,
            'goal_id' => $goal->id,
            'title' => 'Issue '.$status,
            'status' => $status,
        ]);
    }

    #[Test]
    public function progress_rolls_up_from_linked_issues(): void
    {
        $goal = $this->goals->create($this->project, ['title' => 'Ship v1']);
        $this->issue($goal, 'done');
        $this->issue($goal, 'done');
        $this->issue($goal, 'todo');
        $this->issue($goal, 'in_progress');

        $goal = $this->goals->recomputeProgress($goal);

        $this->assertSame(50, $goal->progress); // 2 of 4 done
    }

    #[Test]
    public function progress_from_numeric_metric(): void
    {
        $goal = $this->goals->create($this->project, [
            'title' => 'Reach $1M MRR',
            'target_metric' => '$1M MRR',
            'target_value' => 1_000_000,
        ]);

        $goal = $this->goals->updateMetric($goal, 250_000);

        $this->assertSame(25, $goal->progress);
    }

    #[Test]
    public function parent_progress_averages_children_and_bubbles_up(): void
    {
        $parent = $this->goals->create($this->project, ['title' => 'Launch']);
        $childA = $this->goals->create($this->project, ['title' => 'Backend'], $parent);
        $childB = $this->goals->create($this->project, ['title' => 'Frontend'], $parent);

        // Complete all of child A's issues -> child A 100%, parent should become 50%.
        $this->issue($childA, 'done');
        $this->goals->recomputeProgress($childA);

        $parent->refresh();
        $this->assertSame(100, $childA->refresh()->progress);
        $this->assertSame(50, $parent->progress); // avg(100, 0)

        // Complete child B too -> parent 100% and marked achieved.
        $this->issue($childB, 'done');
        $this->goals->recomputeProgress($childB);

        $parent->refresh();
        $this->assertSame(100, $parent->progress);
        $this->assertSame('achieved', $parent->status);
    }

    #[Test]
    public function goal_is_marked_achieved_at_full_progress(): void
    {
        $goal = $this->goals->create($this->project, ['title' => 'Done thing']);
        $this->issue($goal, 'done');

        $goal = $this->goals->recomputeProgress($goal);

        $this->assertSame(100, $goal->progress);
        $this->assertSame('achieved', $goal->status);
    }

    #[Test]
    public function attach_issue_links_and_recomputes(): void
    {
        $goal = $this->goals->create($this->project, ['title' => 'Linkable']);
        $issue = WorkIssue::query()->create([
            'project_id' => $this->project->id,
            'title' => 'standalone',
            'status' => 'done',
        ]);

        $goal = $this->goals->attachIssue($goal, $issue);

        $this->assertSame($goal->id, $issue->refresh()->goal_id);
        $this->assertSame(100, $goal->progress);
    }

    #[Test]
    public function summary_reports_issue_counts(): void
    {
        $goal = $this->goals->create($this->project, ['title' => 'Tracked', 'target_metric' => 'x']);
        $this->issue($goal, 'done');
        $this->issue($goal, 'todo');

        $summary = $this->goals->summary($goal);

        $this->assertSame(2, $summary['issues_total']);
        $this->assertSame(1, $summary['issues_done']);
        $this->assertSame('x', $summary['target_metric']);
    }
}
