<?php

namespace Tests\Unit;

use App\Services\Orchestrator\OrchestratorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrchestratorRevisionProfileTest extends TestCase
{
    #[Test]
    public function legacy_mode_keeps_cheap_profile_on_first_revision(): void
    {
        $this->assertSame('default', OrchestratorService::revisionProfileKey('default', 0, false));
        $this->assertSame('backend', OrchestratorService::revisionProfileKey('backend', 0, false));
    }

    #[Test]
    public function legacy_mode_escalates_from_round_two(): void
    {
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('default', 1, false));
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('frontend_ui', 2, false));
    }

    #[Test]
    public function early_escalation_upgrades_the_first_revision_round(): void
    {
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('default', 0, true));
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('backend', 0, true));
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('frontend_ui', 0, true));
    }

    #[Test]
    public function non_escalatable_profiles_are_never_changed(): void
    {
        $this->assertSame('high_risk', OrchestratorService::revisionProfileKey('high_risk', 0, true));
        $this->assertSame('devops', OrchestratorService::revisionProfileKey('devops', 1, true));
        $this->assertSame('none', OrchestratorService::revisionProfileKey('none', 3, false));
    }

    #[Test]
    public function first_pass_keeps_cheap_profile_for_normal_tasks(): void
    {
        $route = ['executor_profile' => 'default', 'risk_level' => 'low'];
        $plan = ['confidence' => 0.9];

        $this->assertSame('default', OrchestratorService::firstPassProfileKey('default', $route, $plan));
        $this->assertSame('backend', OrchestratorService::firstPassProfileKey('backend', $route, $plan));
    }

    #[Test]
    public function first_pass_planner_cannot_downgrade_router_high_risk_decision(): void
    {
        $route = ['executor_profile' => 'high_risk', 'risk_level' => 'medium'];

        $this->assertSame('high_risk', OrchestratorService::firstPassProfileKey('default', $route, []));
    }

    #[Test]
    public function first_pass_escalates_on_high_route_risk(): void
    {
        $route = ['executor_profile' => 'default', 'risk_level' => 'high'];

        $this->assertSame('high_risk', OrchestratorService::firstPassProfileKey('backend', $route, ['confidence' => 0.95]));
    }

    #[Test]
    public function first_pass_escalates_on_low_confidence_plan(): void
    {
        $route = ['executor_profile' => 'default', 'risk_level' => 'low'];

        $this->assertSame('high_risk', OrchestratorService::firstPassProfileKey('frontend_ui', $route, ['confidence' => 0.4]));
        $this->assertSame('frontend_ui', OrchestratorService::firstPassProfileKey('frontend_ui', $route, ['confidence' => 0.5]));
    }

    #[Test]
    public function first_pass_leaves_devops_none_and_high_risk_untouched(): void
    {
        $route = ['executor_profile' => 'default', 'risk_level' => 'high'];

        $this->assertSame('devops', OrchestratorService::firstPassProfileKey('devops', $route, []));
        $this->assertSame('none', OrchestratorService::firstPassProfileKey('none', $route, []));
        $this->assertSame('high_risk', OrchestratorService::firstPassProfileKey('high_risk', $route, []));
    }
}
