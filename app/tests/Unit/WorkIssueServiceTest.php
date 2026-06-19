<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Models\BosskuAi\WorkIssue;
use App\Services\Company\WorkIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkIssueServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_idempotent_work_issues_from_an_approved_plan(): void
    {
        Setting::setValue('staff_auto_issue_generation_enabled', '1');
        $project = $this->project();
        $run = Run::factory()->create(['prompt' => 'Build checkout settings page']);

        $first = app(WorkIssueService::class)->createFromApprovedPlan($run, $this->plan(), $project);
        $second = app(WorkIssueService::class)->createFromApprovedPlan($run, $this->plan(), $project);

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
        $this->assertSame(2, WorkIssue::query()->where('run_id', $run->id)->count());
        $this->assertSame('todo', $first[0]->status);
        $this->assertSame('approved', $first[0]->approval_state);
        $this->assertSame('tech-lead', $first[0]->assignee_role_slug);
        $this->assertNotNull($first[0]->assignee_agent_id);
    }

    #[Test]
    public function it_enqueues_wakeup_requests_when_wakeups_enabled(): void
    {
        Setting::setValue('staff_auto_issue_generation_enabled', '1');
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('agent_wakeups_enabled', '1');
        $project = $this->project();
        $run = Run::factory()->create(['prompt' => 'Build checkout settings page']);

        app(WorkIssueService::class)->createFromApprovedPlan($run, $this->plan(), $project);

        $this->assertGreaterThan(0, \App\Models\BosskuAi\AgentWakeupRequest::query()->count());
    }

    #[Test]
    public function it_does_not_enqueue_wakeups_without_an_assignee_agent(): void
    {
        Setting::setValue('staff_auto_issue_generation_enabled', '1');
        Setting::setValue('company_staff_enabled', '0');
        Setting::setValue('agent_wakeups_enabled', '1');
        $project = $this->project();
        $run = Run::factory()->create(['prompt' => 'Build checkout settings page']);

        $issues = app(WorkIssueService::class)->createFromApprovedPlan($run, $this->plan(), $project);

        $this->assertCount(2, $issues);
        $this->assertNull($issues[0]->assignee_agent_id);
        $this->assertSame(0, \App\Models\BosskuAi\AgentWakeupRequest::query()->count());
    }

    #[Test]
    public function it_skips_issue_generation_when_disabled(): void
    {
        Setting::setValue('staff_auto_issue_generation_enabled', '0');
        $project = $this->project();
        $run = Run::factory()->create(['prompt' => 'Build checkout settings page']);

        $issues = app(WorkIssueService::class)->createFromApprovedPlan($run, $this->plan(), $project);

        $this->assertSame([], $issues);
        $this->assertSame(0, WorkIssue::query()->count());
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        return [
            'checklist' => [
                ['id' => 'plan-1', 'title' => 'Implement settings UI', 'description' => 'Create the form.', 'owner' => 'executor'],
                ['id' => 'plan-2', 'title' => 'Verify settings UI', 'description' => 'Run focused tests.', 'owner' => 'auditor'],
            ],
            'staff_council' => [
                'issue_breakdown' => [
                    ['plan_item_id' => 'plan-1', 'assignee_role_slug' => 'tech-lead', 'priority' => 'high'],
                    ['plan_item_id' => 'plan-2', 'assignee_role_slug' => 'qa', 'priority' => 'medium'],
                ],
            ],
        ];
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Work Issue Project',
            'host_path' => sys_get_temp_dir(),
            'container_path' => sys_get_temp_dir(),
            'is_active' => true,
        ]);
    }
}
