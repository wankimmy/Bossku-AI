<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\RunEventFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunEventFactoryTest extends TestCase
{
    #[Test]
    public function planner_done_payload_contains_visual_workflow_fields(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000001',
            'prompt' => 'Improve controller',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->plannerDone(
            $run,
            ['task_summary' => 'Improve controller', 'checklist' => [['id' => 'plan-1', 'title' => 'Inspect files', 'owner' => 'executor', 'status' => 'pending']]],
            'kimi-k2.6',
            321,
            44
        );

        $this->assertSame('planner_done', $payload['type']);
        $this->assertSame('orchestrator', $payload['agent']);
        $this->assertSame('executor', $payload['to_agent']);
        $this->assertSame('reasoning', $payload['model_role']);
        $this->assertSame('kimi-k2.6', $payload['model']);
        $this->assertSame('Improve controller', $payload['artifacts']['plan']['task_summary']);
        $this->assertSame('Inspect files', $payload['artifacts']['checklist'][0]['title']);
        $this->assertSame(321, $payload['latency_ms']);
        $this->assertSame(44, $payload['token_estimate']);
    }

    #[Test]
    public function council_review_done_payload_contains_review_artifact(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000005',
            'prompt' => 'Improve planner review',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->councilReviewDone($run, [
            'status' => 'completed',
            'voices' => [
                ['id' => 'skeptic', 'label' => 'Skeptic', 'position' => 'Keep V1 bounded.'],
            ],
            'consensus' => 'Reuse planner review.',
            'strongest_dissent' => 'Keep V1 bounded.',
            'recommended_adjustments' => ['Show dissent before approval.'],
            'stop_conditions' => ['Stop after configured revision rounds.'],
        ]);

        $this->assertSame('council_review_done', $payload['type']);
        $this->assertSame('orchestrator', $payload['agent']);
        $this->assertSame('planner', $payload['to_agent']);
        $this->assertSame('reasoning', $payload['model_role']);
        $this->assertSame('Keep V1 bounded.', $payload['artifacts']['council_review']['strongest_dissent']);
        $this->assertSame('Council review prepared 1 voice(s).', $payload['summary']);
    }

    #[Test]
    public function staff_council_done_payload_contains_staff_artifacts(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000006',
            'prompt' => 'Build company staff MVP',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->staffCouncilDone($run, [
            'status' => 'completed',
            'voices' => [
                ['role_slug' => 'tech-lead', 'display_name' => 'Tech Lead', 'position' => 'Keep implementation bounded.'],
            ],
            'staff_recommendations' => ['Create approved work issues from plan items.'],
            'issue_breakdown' => [
                ['plan_item_id' => 'plan-1', 'assignee_role_slug' => 'tech-lead', 'priority' => 'high'],
            ],
            'stop_conditions' => ['Wait for CEO approval before starting more work.'],
        ]);

        $this->assertSame('staff_council_done', $payload['type']);
        $this->assertSame('orchestrator', $payload['agent']);
        $this->assertSame('planner', $payload['to_agent']);
        $this->assertSame('reasoning', $payload['model_role']);
        $this->assertSame('tech-lead', $payload['artifacts']['staff_council']['voices'][0]['role_slug']);
        $this->assertSame('Staff council prepared 1 voice(s).', $payload['summary']);
    }

    #[Test]
    public function specialist_agent_selected_payload_contains_match_metadata(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000007',
            'prompt' => 'Write SEO copy',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;
        $agent = new \App\Models\BosskuAi\SpecialistAgent([
            'role_slug' => 'seo-writer',
            'display_name' => 'SEO Writer',
        ]);

        $payload = $factory->specialistAgentSelected($run, $agent, [
            'match_score' => 12,
            'match_reason' => 'intent_role',
            'intent' => 'seo',
        ]);

        $this->assertSame('specialist_agent_selected', $payload['type']);
        $this->assertSame('seo-writer', $payload['agent']);
        $this->assertSame('intent_role', $payload['artifacts']['specialist_agent']['match_reason']);
    }

    #[Test]
    public function ai_council_done_payload_contains_council_artifact(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000008',
            'prompt' => 'Marketing positioning',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->aiCouncilDone($run, [
            'status' => 'completed',
            'intent' => 'marketing',
            'voices' => [
                ['role_slug' => 'marketing-manager', 'display_name' => 'Marketing Manager', 'critique' => 'Clarify audience.'],
            ],
            'consensus' => 'Council reviewed the draft.',
        ]);

        $this->assertSame('ai_council_done', $payload['type']);
        $this->assertSame('marketing', $payload['artifacts']['ai_council']['intent']);
        $this->assertSame('Council reviewed the draft.', $payload['message']);
    }

    #[Test]
    public function metadata_shape_is_consistent_with_sse_payload(): void
    {
        $factory = new RunEventFactory;
        $metadata = $factory->metadata(
            agent: 'executor',
            modelRole: 'coding',
            summary: 'Executor changed files.',
            message: 'Sending changes to auditor.',
            artifacts: ['files_changed' => [['path' => 'app/Foo.php', 'change_type' => 'modified']]],
            fromAgent: 'executor',
            toAgent: 'auditor'
        );

        $this->assertSame('executor', $metadata['agent']);
        $this->assertSame('auditor', $metadata['to_agent']);
        $this->assertSame('coding', $metadata['model_role']);
        $this->assertSame('Executor changed files.', $metadata['summary']);
        $this->assertSame('app/Foo.php', $metadata['artifacts']['files_changed'][0]['path']);
    }

    #[Test]
    public function executor_done_payload_exposes_checklist_status(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000004',
            'prompt' => 'Scaffold Nuxt app',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->executorDone(
            $run,
            [
                'status' => 'partial',
                'patch_summary' => 'Waiting for npm install.',
                'checklist_status' => [[
                    'id' => 'plan-1',
                    'status' => 'partial',
                    'notes' => 'Blocked until npm install output is provided.',
                ]],
            ],
            'qwen3-coder-next',
            123,
            45
        );

        $this->assertSame('executor_step_done', $payload['type']);
        $this->assertSame('partial', $payload['artifacts']['checklist_status'][0]['status']);
        $this->assertSame('plan-1', $payload['artifacts']['checklist_status'][0]['id']);
    }

    #[Test]
    public function post_memory_eval_done_payload_reports_review_metadata(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000002',
            'prompt' => 'Improve controller',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->postMemoryEvalDone(
            $run,
            [
                'score' => 0.86,
                'verdict' => 'pass',
                'summary' => 'Final response, proof, and memory capture are aligned.',
                'recommendation' => 'Keep the current memory template.',
                'dimensions' => [],
            ],
            'mock-evaluator',
            42,
            11
        );

        $this->assertSame('post_memory_eval_done', $payload['type']);
        $this->assertSame('evaluator', $payload['agent']);
        $this->assertSame('memory', $payload['from_agent']);
        $this->assertSame('system', $payload['to_agent']);
        $this->assertSame('review', $payload['model_role']);
        $this->assertSame('mock-evaluator', $payload['model']);
        $this->assertSame(0.86, $payload['artifacts']['evaluation']['score']);
        $this->assertSame('pass', $payload['artifacts']['evaluation']['verdict']);
        $this->assertSame('Keep the current memory template.', $payload['artifacts']['evaluation']['recommendation']);
        $this->assertSame(42, $payload['latency_ms']);
        $this->assertSame(11, $payload['token_estimate']);
    }

    #[Test]
    public function run_completed_uses_short_path_agent_for_direct_answer_workflow(): void
    {
        $run = new Run([
            'id' => '00000000-0000-0000-0000-000000000003',
            'prompt' => 'test',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;

        $payload = $factory->runCompleted(
            $run,
            'BosskuAI is running.',
            25,
            8,
            ['workflow' => 'direct_answer'],
            ['direct_answer' => 'mock-direct', 'router' => 'mock-router'],
        );

        $this->assertSame('run_completed', $payload['type']);
        $this->assertSame('direct_answer', $payload['agent']);
        $this->assertSame('direct_answer', $payload['from_agent']);
        $this->assertSame('system', $payload['to_agent']);
        $this->assertSame('fast', $payload['model_role']);
        $this->assertSame('mock-direct', $payload['model']);
        $this->assertSame('BosskuAI is running.', $payload['output']);
    }
}
