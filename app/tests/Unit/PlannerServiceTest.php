<?php

namespace Tests\Unit;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Orchestrator\PlannerService;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannerServiceTest extends TestCase
{
    #[Test]
    public function planner_prompt_requires_target_files_tests_and_audit_handoff(): void
    {
        $captured = [];

        $fallback = $this->mock(ModelFallbackService::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('chatWithFallbacks')
                ->once()
                ->withArgs(function (
                    array $models,
                    array $messages,
                    float $temperature,
                    int $retryCount,
                    string $role,
                    mixed $isValidJson,
                    ?int $maxTokens
                ) use (&$captured): bool {
                    $captured = compact('models', 'messages', 'temperature', 'retryCount', 'role', 'maxTokens');

                    return $role === 'orchestrator'
                        && $temperature === 0.2
                        && $retryCount === 0
                        && $maxTokens === 8192
                        && is_callable($isValidJson)
                        && count($messages) === 2
                        && ($messages[0]['role'] ?? '') === 'system'
                        && ($messages[1]['role'] ?? '') === 'user';
                })
                ->andReturn([
                    'parsed' => [
                        'summary' => 'Implement the knowledge tab.',
                        'task_summary' => 'Implement the knowledge tab.',
                        'goal' => 'Add knowledge import flows.',
                        'risk_level' => 'medium',
                        'selected_skill' => 'nuxt',
                        'memory_strategy' => 'read_only',
                        'expected_artifacts' => ['files_changed', 'audit_findings', 'final_summary'],
                        'target_file_list' => [
                            ['path' => 'web/pages/knowledge.vue', 'reason' => 'new page'],
                        ],
                        'allow_broad_repo_scan' => false,
                        'executor_profile' => 'frontend_ui',
                        'suggested_tests' => ['npm run test -- knowledge'],
                        'risk_notes' => ['UI route needs smoke coverage.'],
                        'constraints' => ['Do not change API shapes.'],
                        'handoff_message' => 'Update web/pages/knowledge.vue and report the exact smoke tests you ran.',
                        'execution_mode' => 'delegate_executor',
                        'user_commands' => [],
                        'checklist' => [
                            [
                                'id' => 'plan-1',
                                'title' => 'Inspect target files',
                                'description' => 'Read web/pages/knowledge.vue and related sidebar wiring.',
                                'owner' => 'executor',
                                'status' => 'pending',
                            ],
                            [
                                'id' => 'plan-2',
                                'title' => 'Audit the result',
                                'description' => 'Verify the UI contract and smoke coverage.',
                                'owner' => 'auditor',
                                'status' => 'pending',
                            ],
                        ],
                    ],
                    'model_used' => 'kimi-k2.6',
                    'model_resolved' => 'kimi-k2.6',
                    'fallback_used' => false,
                ]);
        });

        $routing = $this->mock(ModelRoutingConfig::class, function ($mock) {
            $mock->shouldReceive('orchestrator')->once()->andReturn([
                'primary' => 'kimi-k2.6',
                'fallback' => [],
                'retry_count' => 0,
                'temperature' => 0.2,
                'max_tokens' => 8192,
            ]);
        });

        $settings = $this->mock(RuntimeSettings::class);
        $projects = $this->mock(ProjectService::class, function ($mock) {
            $mock->shouldReceive('evidenceRuleForPrompt')
                ->once()
                ->andReturn('Evidence rule.');
        });
        $discovery = $this->mock(ProjectFileDiscovery::class, function ($mock) {
            $mock->shouldReceive('repoIndexForPlanner')
                ->once()
                ->andReturn('Repository root: /workspace');
        });

        $service = new PlannerService($fallback, $routing, $settings, $projects, $discovery);
        $out = $service->plan(
            'Add a knowledge tab.',
            [['type' => 'memory', 'summary' => 'existing knowledge']],
            ['primary_skill' => ['name' => 'nuxt']],
            ['workflow' => 'orchestrator_executor', 'skill' => 'nuxt', 'risk_level' => 'medium', 'needs_executor' => true]
        );

        $system = (string) ($captured['messages'][0]['content'] ?? '');
        $user = (string) ($captured['messages'][1]['content'] ?? '');

        $this->assertStringContainsString('Output ONLY valid JSON', $system);
        $this->assertStringContainsString('target_file_list', $system);
        $this->assertStringContainsString('key_design_decisions', $system);
        $this->assertStringContainsString('flow_diagram', $system);
        $this->assertStringContainsString('flowchart TD', $system);
        $this->assertStringContainsString('executor-ready and audit-ready', $system);
        $this->assertStringContainsString('If a path is not supported by repo evidence', $system);
        $this->assertStringContainsString('leave target_file_list empty rather than inventing one', $system);
        $this->assertStringContainsString('handoff_message must tell the executor', $system);
        $this->assertStringContainsString('Add a knowledge tab.', $user);
        $this->assertStringContainsString('existing knowledge', $user);
        $this->assertStringContainsString('Repository root: \/workspace', $user);

        $this->assertSame('Implement the knowledge tab.', $out['summary']);
        $this->assertSame('Add knowledge import flows.', $out['goal']);
        $this->assertSame('nuxt', $out['selected_skill']);
        $this->assertFalse($out['allow_broad_repo_scan']);
        $this->assertSame('delegate_executor', $out['execution_mode']);
        $this->assertSame('web/pages/knowledge.vue', $out['target_file_list'][0]['path']);
        $this->assertSame('Inspect target files', $out['checklist'][0]['title']);
        $this->assertSame('Audit the result', $out['checklist'][1]['title']);
    }

    #[Test]
    public function planner_fills_default_checklist_when_llm_returns_sparse_json(): void
    {
        $fallback = $this->mock(ModelFallbackService::class, function ($mock) {
            $mock->shouldReceive('chatWithFallbacks')
                ->once()
                ->andReturn([
                    'parsed' => [
                        'summary' => 'Sparse',
                        'task_summary' => 'Sparse',
                        'goal' => 'Do the thing',
                        'selected_skill' => 'general',
                        'target_file_list' => [],
                        'allow_broad_repo_scan' => true,
                        'checklist' => [],
                        'handoff_message' => 'Proceed.',
                    ],
                    'model_used' => 'kimi-k2.6',
                    'model_resolved' => 'kimi-k2.6',
                    'fallback_used' => false,
                ]);
        });

        $routing = $this->mock(ModelRoutingConfig::class, function ($mock) {
            $mock->shouldReceive('orchestrator')->once()->andReturn([
                'primary' => 'kimi-k2.6',
                'fallback' => [],
                'retry_count' => 0,
                'temperature' => 0.2,
                'max_tokens' => 8192,
            ]);
        });

        $settings = $this->mock(RuntimeSettings::class);
        $projects = $this->mock(ProjectService::class, function ($mock) {
            $mock->shouldReceive('evidenceRuleForPrompt')->once()->andReturn('Evidence rule.');
        });
        $discovery = $this->mock(ProjectFileDiscovery::class, function ($mock) {
            $mock->shouldReceive('repoIndexForPlanner')->once()->andReturn('Repository root: /workspace');
        });

        $service = new PlannerService($fallback, $routing, $settings, $projects, $discovery);
        $out = $service->plan(
            'Do something sparse.',
            [],
            ['primary_skill' => ['name' => 'general']],
            ['workflow' => 'orchestrator_only', 'skill' => 'general', 'risk_level' => 'low', 'needs_executor' => false]
        );

        $this->assertCount(5, $out['checklist']);
        $this->assertSame('plan-1', $out['checklist'][0]['id']);
        $this->assertSame('Inspect relevant files', $out['checklist'][0]['title']);
        $this->assertSame('answer_only', $out['execution_mode']);
    }

    #[Test]
    public function planner_normalizes_flow_diagram_and_design_decisions(): void
    {
        $fallback = $this->mock(ModelFallbackService::class, function ($mock) {
            $mock->shouldReceive('chatWithFallbacks')
                ->once()
                ->andReturn([
                    'parsed' => [
                        'summary' => 'Plan',
                        'task_summary' => 'Plan',
                        'goal' => 'Ship feature',
                        'selected_skill' => 'general',
                        'target_file_list' => [],
                        'checklist' => [],
                        'handoff_message' => 'Go.',
                        'key_design_decisions' => ['Reuse existing planner event shape', ''],
                        'flow_diagram' => "```mermaid\nflowchart TD\n  A[Start] --> B[Done]\n```",
                        'flow_steps' => ['Start', 'Done'],
                    ],
                    'model_used' => 'kimi-k2.6',
                    'model_resolved' => 'kimi-k2.6',
                    'fallback_used' => false,
                ]);
        });

        $routing = $this->mock(ModelRoutingConfig::class, function ($mock) {
            $mock->shouldReceive('orchestrator')->once()->andReturn([
                'primary' => 'kimi-k2.6',
                'fallback' => [],
                'retry_count' => 0,
                'temperature' => 0.2,
                'max_tokens' => 8192,
            ]);
        });

        $settings = $this->mock(RuntimeSettings::class);
        $projects = $this->mock(ProjectService::class, function ($mock) {
            $mock->shouldReceive('evidenceRuleForPrompt')->once()->andReturn('Evidence rule.');
        });
        $discovery = $this->mock(ProjectFileDiscovery::class, function ($mock) {
            $mock->shouldReceive('repoIndexForPlanner')->once()->andReturn('Repository root: /workspace');
        });

        $service = new PlannerService($fallback, $routing, $settings, $projects, $discovery);
        $out = $service->plan('Ship it.', [], ['primary_skill' => ['name' => 'general']], [
            'workflow' => 'orchestrator_executor',
            'skill' => 'general',
            'risk_level' => 'low',
            'needs_executor' => true,
        ]);

        $this->assertSame(['Reuse existing planner event shape'], $out['key_design_decisions']);
        $this->assertStringContainsString('flowchart TD', $out['flow_diagram']);
        $this->assertStringNotContainsString('```', $out['flow_diagram']);
        $this->assertSame(['Start', 'Done'], $out['flow_steps']);
    }
}
