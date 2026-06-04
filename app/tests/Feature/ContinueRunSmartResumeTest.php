<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Orchestrator\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContinueRunSmartResumeTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bossku_smart_resume_'.uniqid();
        File::ensureDirectoryExists($this->root);
        File::put($this->root.'/artisan', '');

        Project::query()->create([
            'name' => 'Resume Test Project',
            'host_path' => $this->root,
            'container_path' => $this->root,
            'is_active' => true,
        ]);

        Setting::setValue('memory_storage_enabled', '0');
        Setting::setValue('learning_auto_promote_enabled', '0');
        Setting::setValue('orchestrator_clarification_mode', 'smart');
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function rescoping_answer_on_executor_escalation_replans_on_same_run(): void
    {
        $conversation = [['role' => 'user', 'content' => 'Build the feature']];
        $run = $this->pausedExecutorEscalationRun($conversation);

        $this->mock(SkillRouterService::class, function ($mock): void {
            $mock->shouldReceive('route')
                ->once()
                ->andReturn(['primary_skill' => ['name' => 'generic', 'content' => '']]);
        });

        $this->mock(PlannerService::class, function ($mock): void {
            $mock->shouldReceive('plan')
                ->once()
                ->andReturn([
                    'goal' => 'Stage 1 only',
                    'checklist' => [],
                    'planner_questions' => [],
                    'summary' => 'Narrowed scope',
                ]);
        });

        $events = [];
        $result = app(OrchestratorService::class)->continueRun(
            $run->id,
            [['question_id' => 'esc_q1', 'free_text' => 'start with stage 1 first']],
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $types = array_column($events, 'type');
        $this->assertContains('planner_replan_requested', $types);
        $this->assertContains('planner_started', $types);
        $this->assertSame($run->id, $result['run_id'] ?? $run->id);
    }

    #[Test]
    public function proceed_answer_on_executor_escalation_retries_executor_without_replan(): void
    {
        $run = $this->pausedExecutorEscalationRun([['role' => 'user', 'content' => 'Build the feature']]);

        $this->mock(PlannerService::class, function ($mock): void {
            $mock->shouldNotReceive('plan');
        });

        $this->mock(ExecutorService::class, function ($mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'summary' => 'Retried with guidance',
                    'patch_summary' => 'done',
                    'files_changed' => [],
                    'latency_ms' => 10,
                ]);
        });

        $events = [];
        app(OrchestratorService::class)->continueRun(
            $run->id,
            [['question_id' => 'esc_q1', 'free_text' => 'yes, proceed']],
            function (array $event) use (&$events): void {
                $events[] = $event;
            },
        );

        $types = array_column($events, 'type');
        $this->assertContains('executor_step_started', $types);
        $this->assertNotContains('planner_replan_requested', $types);
        $this->assertNotContains('planner_started', $types);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    private function pausedExecutorEscalationRun(array $conversation): Run
    {
        return Run::factory()->create([
            'status' => 'awaiting_input',
            'prompt' => 'Build the feature',
            'metadata' => [
                'conversation' => $conversation,
                'checkpoint' => [
                    'stage' => 'executor_escalation',
                    'clarification' => [
                        'summary' => 'Executor needs input',
                        'questions' => [
                            ['id' => 'esc_q1', 'prompt' => 'How should we proceed?', 'options' => []],
                        ],
                    ],
                    'pipeline' => [
                        'user_prompt' => 'Build the feature',
                        'agent_prompt' => 'Build the feature',
                        'effective_prompt' => 'Build the feature',
                        'conversation' => $conversation,
                        'model_route' => [
                            'workflow' => 'orchestrator_executor_auditor',
                            'task_type' => 'implementation',
                        ],
                        'models_resolved' => [
                            'router' => 'mock-router',
                            'planner' => 'mock-planner',
                            'executor' => 'mock-executor',
                        ],
                        'router_ctx' => ['primary_skill' => ['name' => 'generic']],
                        'mem_payload' => [],
                        'plan' => [
                            'goal' => 'Build the feature',
                            'checklist' => [],
                        ],
                        'exec_result' => [
                            'needs_user_input' => true,
                            'summary' => 'Blocked',
                        ],
                        'workflow' => 'orchestrator_executor_auditor',
                        'step' => ['id' => 1, 'title' => 'Execute plan'],
                        'skill_name' => 'generic',
                        'skill_row' => ['name' => 'generic', 'content' => ''],
                        'rule_lines' => [],
                        'playbook_excerpt' => '',
                        'checklist_excerpt' => '',
                        'preflight_reads' => [],
                        'exec_profile_key' => 'default',
                        'token_acc' => 0,
                        't_run' => microtime(true),
                    ],
                ],
            ],
        ]);
    }
}
