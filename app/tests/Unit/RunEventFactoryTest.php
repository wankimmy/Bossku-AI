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
