<?php

namespace Tests\Unit;

use App\Services\Orchestrator\DesignerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignerServiceTest extends TestCase
{
    #[Test]
    public function it_runs_for_frontend_ui_profile(): void
    {
        $svc = app(DesignerService::class);

        $this->assertTrue($svc->shouldRun(['executor_profile' => 'frontend_ui'], 'frontend_ui'));
    }

    #[Test]
    public function it_runs_when_plan_flags_design_phase(): void
    {
        $svc = app(DesignerService::class);

        $this->assertTrue($svc->shouldRun(['design_phase_required' => true], 'default'));
    }

    #[Test]
    public function it_skips_for_plain_backend_work(): void
    {
        $svc = app(DesignerService::class);

        $this->assertFalse($svc->shouldRun(['executor_profile' => 'backend'], 'backend'));
    }
}
