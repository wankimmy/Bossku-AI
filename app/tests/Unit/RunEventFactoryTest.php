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
        $run = Run::query()->create([
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
}
