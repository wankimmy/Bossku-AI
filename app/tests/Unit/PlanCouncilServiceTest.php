<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Orchestrator\PlanCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanCouncilServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_bounded_council_review_from_plan_context(): void
    {
        Setting::setValue('council_plan_review_enabled', '1');
        Setting::setValue('max_revision_rounds', '2');
        Setting::setValue('max_approval_review_rounds', '3');

        $review = app(PlanCouncilService::class)->review(
            $this->plan(),
            ['workflow' => 'orchestrator_executor_auditor', 'needs_executor' => true, 'risk_level' => 'medium'],
            ['primary_skill' => ['name' => 'generic']],
            120,
            [
                'summary' => 'Specialist recommends keeping the first slice narrow.',
                'pitfalls' => ['Do not expand into unrelated files.'],
                'handoff_to_executor' => 'Start with hello-world.txt only.',
            ],
        );

        $this->assertSame('completed', $review['status']);
        $this->assertSame(['architect', 'skeptic', 'pragmatist', 'critic', 'specialist'], array_column($review['voices'], 'id'));
        $this->assertStringContainsString('hello-world.txt', $review['consensus']);
        $this->assertStringContainsString('approval rounds: 3', implode(' ', $review['stop_conditions']));
        $this->assertStringContainsString('revision rounds: 2', implode(' ', $review['stop_conditions']));
        $this->assertContains('Confirm the plan before execution starts.', $review['recommended_adjustments']);
    }

    #[Test]
    public function it_skips_when_token_budget_is_already_exhausted(): void
    {
        config(['bossku.token_budget_per_run' => 100]);
        Setting::setValue('council_plan_review_enabled', '1');

        $review = app(PlanCouncilService::class)->review(
            $this->plan(),
            ['workflow' => 'orchestrator_executor', 'needs_executor' => true],
            ['primary_skill' => ['name' => 'generic']],
            100,
        );

        $this->assertSame('skipped', $review['status']);
        $this->assertSame('token_budget', $review['reason']);
        $this->assertSame([], $review['voices']);
    }

    #[Test]
    public function it_skips_when_disabled_in_settings(): void
    {
        Setting::setValue('council_plan_review_enabled', '0');

        $this->assertFalse(app(RuntimeSettings::class)->councilPlanReviewEnabled());

        $review = app(PlanCouncilService::class)->review(
            $this->plan(),
            ['workflow' => 'orchestrator_executor', 'needs_executor' => true],
            ['primary_skill' => ['name' => 'generic']],
            0,
        );

        $this->assertSame('skipped', $review['status']);
        $this->assertSame('disabled', $review['reason']);
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        return [
            'goal' => 'Create a hello-world file.',
            'summary' => 'Master plan ready.',
            'confidence' => 0.82,
            'target_file_list' => [
                ['path' => 'hello-world.txt', 'reason' => 'Requested file'],
            ],
            'risk_notes' => ['File write needs approval before apply.'],
            'constraints' => ['Keep the change scoped.'],
            'checklist' => [
                ['id' => 'plan-1', 'title' => 'Create file', 'owner' => 'executor', 'status' => 'pending'],
            ],
        ];
    }
}
