<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\RunEventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Agent escalation uses direct questions (no second ClarificationService LLM pass).
 */
class AgentEscalationTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function agent_escalation_event_carries_executor_proof(): void
    {
        $run = Run::query()->create([
            'prompt' => 'test escalation',
            'status' => 'running',
            'metadata' => [],
        ]);

        $factory = new RunEventFactory;
        $evt = $factory->clarificationRequested(
            $run,
            [
                [
                    'id' => 'escalation_1',
                    'prompt' => 'Delete routes/api.php?',
                    'options' => [
                        ['id' => 'yes', 'label' => 'Yes, delete', 'recommendation' => false],
                        ['id' => 'no', 'label' => 'No, skip', 'recommendation' => true],
                    ],
                ],
            ],
            'executor_escalation',
            'Executor needs approval for destructive change.',
            [],
            'executor',
            'executor_escalation',
            [
                'proof_files' => ['routes/api.php'],
                'blockers' => ['destructive without consent'],
            ],
        );

        $this->assertSame('awaiting_input', $evt['status']);
        $this->assertSame('executor_escalation', $evt['stage']);
        $this->assertSame('executor', $evt['from_agent']);
        $this->assertNotEmpty($evt['artifacts']['clarification']['questions']);

        $run->delete();
    }
}
