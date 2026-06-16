<?php

namespace Tests\Feature\Kernel;

use App\Services\Kernel\Pipeline\KernelPipelineCoordinator;
use App\Services\Orchestrator\AuditorService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\FinalReviewerService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PlannerService;
use App\Services\Orchestrator\SecurityAuditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KernelDispatchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function run_dispatches_through_the_kernel_when_flag_is_graph(): void
    {
        config(['bossku.kernel' => 'graph']);

        // A real coordinator wired to mocked pipeline services, bound into the
        // container so OrchestratorService picks it up.
        $planner = Mockery::mock(PlannerService::class);
        $planner->shouldReceive('plan')->andReturn(['steps' => [['skill' => 'demo', 'task' => 't']]]);
        $executor = Mockery::mock(ExecutorService::class);
        $executor->shouldReceive('execute')->andReturn(['status' => 'success', 'output' => 'kernel output']);
        $auditor = Mockery::mock(AuditorService::class);
        $auditor->shouldReceive('auditStep')->andReturn(['verdict' => 'pass']);
        $security = Mockery::mock(SecurityAuditorService::class);
        $security->shouldReceive('audit')->andReturn(['verdict' => 'secure']);
        $final = Mockery::mock(FinalReviewerService::class);
        $final->shouldReceive('review')->andReturn(['summary' => 'reviewed']);

        $this->app->instance(
            KernelPipelineCoordinator::class,
            new KernelPipelineCoordinator($planner, $executor, $auditor, $security, $final),
        );

        $result = $this->app->make(OrchestratorService::class)->run('build a small feature');

        $this->assertTrue($result['kernel'] ?? false);
        $this->assertSame('graph', $result['metadata']['engine'] ?? null);
        $this->assertNotSame('', $result['final_output']);
        // A run row + checkpoints were produced by the kernel path.
        $this->assertDatabaseHas('bossku_ai_runs', ['id' => $result['run_id'], 'status' => 'completed']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
