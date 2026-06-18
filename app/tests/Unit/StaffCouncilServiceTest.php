<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\Company\CompanyStaffService;
use App\Services\Company\StaffCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCouncilServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_bounded_staff_council_review_for_executor_plans(): void
    {
        $project = $this->project();
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('staff_council_enabled', '1');
        Setting::setValue('max_revision_rounds', '2');
        app(CompanyStaffService::class)->seedDefaults($project);

        $review = app(StaffCouncilService::class)->reviewPlan(
            Run::factory()->create(['prompt' => 'Build checkout settings page']),
            $this->plan(),
            ['workflow' => 'orchestrator_executor_auditor', 'needs_executor' => true, 'risk_level' => 'medium'],
            ['primary_skill' => ['name' => 'frontend']],
            0,
            $project,
        );

        $roles = array_column($review['voices'], 'role_slug');

        $this->assertSame('completed', $review['status']);
        $this->assertContains('project-manager', $roles);
        $this->assertContains('tech-lead', $roles);
        $this->assertContains('ui-ux-designer', $roles);
        $this->assertContains('qa', $roles);
        $this->assertContains('security', $roles);
        $this->assertStringContainsString('CEO approval', implode(' ', $review['stop_conditions']));
        $this->assertNotEmpty($review['staff_recommendations']);
        $this->assertSame('tech-lead', $review['issue_breakdown'][0]['assignee_role_slug']);
    }

    #[Test]
    public function it_reviews_writer_deliverables_without_enabling_casual_direct_answer_paths(): void
    {
        $project = $this->project();
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('staff_council_enabled', '1');
        app(CompanyStaffService::class)->seedDefaults($project);

        $writerReview = app(StaffCouncilService::class)->reviewContentDeliverable(
            Run::factory()->create(['prompt' => 'Write SEO launch blog']),
            'Write SEO launch blog',
            'Final blog copy',
            ['workflow' => 'writer_only', 'skill' => 'writing'],
            $project,
        );
        $directReview = app(StaffCouncilService::class)->reviewContentDeliverable(
            Run::factory()->create(['prompt' => 'hello']),
            'hello',
            'Hi',
            ['workflow' => 'direct_answer', 'skill' => 'generic'],
            $project,
        );

        $this->assertSame('completed', $writerReview['status']);
        $this->assertSame('skipped', $directReview['status']);
        $this->assertSame('short_direct_answer', $directReview['reason']);
    }

    #[Test]
    public function it_skips_when_staff_council_is_disabled(): void
    {
        $project = $this->project();
        Setting::setValue('company_staff_enabled', '1');
        Setting::setValue('staff_council_enabled', '0');
        app(CompanyStaffService::class)->seedDefaults($project);

        $review = app(StaffCouncilService::class)->reviewPlan(
            Run::factory()->create(['prompt' => 'Build checkout settings page']),
            $this->plan(),
            ['workflow' => 'orchestrator_executor', 'needs_executor' => true],
            [],
            0,
            $project,
        );

        $this->assertSame('skipped', $review['status']);
        $this->assertSame('disabled', $review['reason']);
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        return [
            'goal' => 'Build checkout settings page',
            'summary' => 'Create a bounded frontend settings enhancement.',
            'target_file_list' => [
                ['path' => 'web/pages/settings/checkout.vue', 'reason' => 'Requested UI'],
            ],
            'risk_notes' => ['Avoid changing payment behavior.'],
            'checklist' => [
                ['id' => 'plan-1', 'title' => 'Create checkout settings UI', 'description' => 'Add form controls.', 'owner' => 'executor', 'status' => 'pending'],
            ],
        ];
    }

    private function project(): Project
    {
        return Project::query()->create([
            'name' => 'Staff Council Project',
            'host_path' => sys_get_temp_dir(),
            'container_path' => sys_get_temp_dir(),
            'is_active' => true,
        ]);
    }
}
