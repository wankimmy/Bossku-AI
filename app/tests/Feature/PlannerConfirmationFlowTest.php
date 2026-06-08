<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Learning\UserSelfLearningService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\ObsidianSyncService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannerConfirmationFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bossku_plan_confirm_'.uniqid();
        File::ensureDirectoryExists($this->root);
        File::put($this->root.'/artisan', '');

        config(['bossku.repo_root' => $this->root]);

        Project::query()->create([
            'name' => 'Planner Confirmation Project',
            'host_path' => $this->root,
            'container_path' => $this->root,
            'is_active' => true,
        ]);

        Setting::setValue('memory_storage_enabled', '0');
        Setting::setValue('learning_auto_promote_enabled', '0');
        Setting::setValue('orchestrator_clarification_mode', 'smart');
        Setting::setValue('orchestrator_plan_confirmation_mode', 'always');
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function executor_workflow_pauses_for_planner_review_before_executor_runs(): void
    {
        $this->mockExecutorRoute();
        $this->mockSkillRouter();
        $this->mockPlanner();
        $this->mock(ExecutorService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });

        $events = [];
        $result = app(OrchestratorService::class)->run(
            'Create hello-world.txt with Hello World',
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $this->assertSame('awaiting_input', $result['status'] ?? null);
        $this->assertSame('planner_review', $result['stage'] ?? null);

        $types = array_column($events, 'type');
        $this->assertContains('planner_done', $types);
        $this->assertContains('clarification_requested', $types);

        $run = Run::query()->findOrFail((string) $result['run_id']);
        $this->assertSame('awaiting_input', $run->status);
        $this->assertSame('planner_review', $run->metadata['checkpoint']['stage'] ?? null);
        $this->assertSame('Master plan ready.', $run->metadata['checkpoint']['pipeline']['plan']['summary'] ?? null);
    }

    #[Test]
    public function approving_planner_review_reuses_stored_plan_without_replanning(): void
    {
        $run = $this->plannerReviewRun();

        $this->mock(PlannerService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('plan');
        });
        $this->mockSkillRouter();
        $this->mockExecutorSuccess();
        $this->mockCompletionServices();

        $events = [];
        $result = app(OrchestratorService::class)->continueRun(
            $run->id,
            [['question_id' => 'planner-review', 'option_id' => 'approve', 'free_text' => 'Approve plan']],
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $this->assertSame($run->id, $result['run_id'] ?? null);
        $this->assertSame('completed', Run::query()->findOrFail($run->id)->status);
        $this->assertContains('executor_step_started', array_column($events, 'type'));
    }

    #[Test]
    public function requesting_changes_on_planner_review_replans_once(): void
    {
        $run = $this->plannerReviewRun();

        $this->mockSkillRouter();
        $this->mock(PlannerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('plan')
                ->once()
                ->withArgs(function (string $agentPrompt): bool {
                    $this->assertStringContainsString('## Plan feedback', $agentPrompt);
                    $this->assertStringNotContainsString('## Code review instructions', $agentPrompt);

                    return true;
                })
                ->andReturn($this->plan(['summary' => 'Revised plan ready.']));
        });
        $this->mock(ExecutorService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('execute');
        });

        $events = [];
        $result = app(OrchestratorService::class)->continueRun(
            $run->id,
            [['question_id' => 'planner-review', 'option_id' => 'revise', 'free_text' => 'Use a smaller first slice']],
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
            'request_changes',
            'Use a smaller first slice',
        );

        $this->assertSame('awaiting_input', $result['status'] ?? null);
        $this->assertSame('planner_review', $result['stage'] ?? null);
        $this->assertContains('planner_replan_requested', array_column($events, 'type'));
    }

    private function mockExecutorRoute(): void
    {
        $route = [
            'task_type' => 'code_generation',
            'audit_mode' => 'standard',
            'risk_level' => 'low',
            'skill' => 'generic',
            'workflow' => 'orchestrator_executor',
            'needs_repo_context' => false,
            'needs_file_edit' => true,
            'needs_test_run' => false,
            'needs_executor' => true,
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'executor_profile' => 'default',
            'memory_mode' => 'none',
            'estimated_token_level' => 'low',
            'reason' => 'Test route.',
        ];

        $this->mock(PromptRouteClassifier::class, function (MockInterface $mock) use ($route): void {
            $mock->shouldReceive('classify')
                ->once()
                ->andReturn([
                    'route' => $route,
                    'models_resolved' => [
                        'router' => 'mock-router',
                        'orchestrator' => 'mock-planner',
                        'executor' => 'mock-executor',
                    ],
                    'router_meta' => ['provider' => 'mock'],
                ]);
        });
    }

    private function mockSkillRouter(): void
    {
        $this->mock(SkillRouterService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('route')
                ->andReturn(['primary_skill' => ['name' => 'generic', 'content' => '']]);
        });
    }

    private function mockPlanner(): void
    {
        $this->mock(PlannerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('plan')
                ->once()
                ->andReturn($this->plan());
        });
    }

    private function mockExecutorSuccess(): void
    {
        $this->mock(ExecutorService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'summary' => 'Executor used stored plan.',
                    'patch_summary' => 'No file changes in test.',
                    'files_changed' => [],
                    'commands_run' => [],
                    'latency_ms' => 5,
                ]);
        });
    }

    private function mockCompletionServices(): void
    {
        $this->mock(UserSelfLearningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processAfterRun')->once()->andReturn([]);
        });
        $this->mock(ObsidianSyncService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once();
        });
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function plan(array $overrides = []): array
    {
        return array_merge([
            'task_summary' => 'Create hello-world file.',
            'summary' => 'Master plan ready.',
            'goal' => 'Create a hello-world file.',
            'selected_skill' => 'generic',
            'risk_level' => 'low',
            'executor_profile' => 'default',
            'execution_mode' => 'delegate_executor',
            'planner_questions' => [],
            'target_file_list' => [['path' => 'hello-world.txt', 'reason' => 'Requested file']],
            'checklist' => [[
                'id' => 'plan-1',
                'title' => 'Create file',
                'description' => 'Create hello-world.txt.',
                'target' => 'hello-world.txt',
                'success_criterion' => 'File exists with Hello World.',
                'owner' => 'executor',
                'status' => 'pending',
            ]],
            'handoff_message' => 'Use the approved plan.',
        ], $overrides);
    }

    private function plannerReviewRun(): Run
    {
        return Run::factory()->create([
            'status' => 'awaiting_input',
            'prompt' => 'Create hello-world.txt with Hello World',
            'metadata' => [
                'checkpoint' => [
                    'stage' => 'planner_review',
                    'clarification' => [
                        'summary' => 'Review the master plan before execution.',
                        'questions' => [
                            ['id' => 'planner-review', 'prompt' => 'Review the master plan.', 'options' => []],
                        ],
                    ],
                    'pipeline' => [
                        'user_prompt' => 'Create hello-world.txt with Hello World',
                        'effective_prompt' => 'Create hello-world.txt with Hello World',
                        'agent_prompt' => 'Create hello-world.txt with Hello World',
                        'conversation' => [],
                        'model_route' => [
                            'workflow' => 'orchestrator_executor',
                            'task_type' => 'code_generation',
                            'skill' => 'generic',
                            'needs_executor' => true,
                            'needs_auditor' => false,
                            'needs_security_auditor' => false,
                            'needs_final_reviewer' => false,
                            'executor_profile' => 'default',
                        ],
                        'models_resolved' => [
                            'router' => 'mock-router',
                            'orchestrator' => 'mock-planner',
                            'executor' => 'mock-executor',
                        ],
                        'router_ctx' => ['primary_skill' => ['name' => 'generic', 'content' => '']],
                        'router_meta' => ['provider' => 'mock'],
                        'mem_payload' => [],
                        'plan' => $this->plan(),
                        'token_acc' => 0,
                        't_run' => microtime(true),
                    ],
                ],
            ],
        ]);
    }
}
