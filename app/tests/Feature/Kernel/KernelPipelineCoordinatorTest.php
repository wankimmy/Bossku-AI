<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Pipeline\KernelPipelineCoordinator;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Orchestrator\AuditorService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\FinalReviewerService;
use App\Services\Orchestrator\PlannerService;
use App\Services\Orchestrator\SecurityAuditorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the kernel pipeline path wiring with service doubles. (Behavioral
 * parity with the live LLM pipeline is validated separately by the eval suite.)
 */
class KernelPipelineCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $calls = [];

    private function coordinator(string $workflow): KernelPipelineCoordinator
    {
        $planner = Mockery::mock(PlannerService::class);
        $planner->shouldReceive('plan')->andReturnUsing(function () {
            $this->calls[] = 'planner';

            return ['steps' => [['skill' => 'demo', 'task' => 'do it']]];
        });

        $executor = Mockery::mock(ExecutorService::class);
        $executor->shouldReceive('execute')->andReturnUsing(function () {
            $this->calls[] = 'executor';

            return ['status' => 'success', 'output' => 'done'];
        });

        $auditor = Mockery::mock(AuditorService::class);
        $auditor->shouldReceive('auditStep')->andReturnUsing(function () {
            $this->calls[] = 'auditor';

            return ['verdict' => 'pass'];
        });

        $security = Mockery::mock(SecurityAuditorService::class);
        $security->shouldReceive('audit')->andReturnUsing(function () {
            $this->calls[] = 'security';

            return ['verdict' => 'secure'];
        });

        $final = Mockery::mock(FinalReviewerService::class);
        $final->shouldReceive('review')->andReturnUsing(function () {
            $this->calls[] = 'final';

            return ['summary' => 'all good'];
        });

        return new KernelPipelineCoordinator($planner, $executor, $auditor, $security, $final);
    }

    #[Test]
    public function executor_only_workflow_runs_planner_then_executor(): void
    {
        $run = Run::query()->create(['prompt' => 'build a thing', 'status' => 'running']);
        $result = $this->coordinator('orchestrator_executor')
            ->run($run, new PipelineContext(prompt: 'build a thing', workflow: 'orchestrator_executor'));

        $this->assertSame(['planner', 'executor'], $this->calls);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('done', $result['final_output']);
        $this->assertTrue($result['kernel']);
        $this->assertSame('completed', $run->refresh()->status);
    }

    #[Test]
    public function full_workflow_runs_every_stage_in_order(): void
    {
        $run = Run::query()->create(['prompt' => 'risky change', 'status' => 'running']);
        $result = $this->coordinator('full')
            ->run($run, new PipelineContext(
                prompt: 'risky change',
                workflow: 'orchestrator_executor_auditor_security_final_reviewer',
            ));

        $this->assertSame(['planner', 'executor', 'auditor', 'security', 'final'], $this->calls);
        $this->assertSame('all good', $result['final_output']);
        $this->assertSame('all good', $run->refresh()->final_output);
    }

    #[Test]
    public function kernel_run_persists_checkpoints_for_resume(): void
    {
        $run = Run::query()->create(['prompt' => 'p', 'status' => 'running']);
        $this->coordinator('orchestrator_executor_auditor')
            ->run($run, new PipelineContext(prompt: 'p', workflow: 'orchestrator_executor_auditor'));

        // input + router + memory + planner + executor + auditor supersteps.
        $this->assertDatabaseHas('bossku_ai_checkpoints', ['thread_id' => $run->id, 'source' => 'input']);
        $this->assertGreaterThanOrEqual(5, \DB::table('bossku_ai_checkpoints')->where('thread_id', $run->id)->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
