<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Hil\ApprovalInterruptBridge;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalInterruptBridgeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function kernel_interrupt_creates_an_approval_then_resumes_on_decision(): void
    {
        $run = Run::query()->create(['prompt' => 'ship it', 'status' => 'running']);
        $bridge = new ApprovalInterruptBridge;

        $graph = (new GraphBuilder(StateSchema::make(['deployed' => new LastValue])))
            ->addNode('deploy', new CallableNode(function (RunState $s, GraphContext $c): array {
                $decision = $c->interrupt('approve:deploy', [
                    'operation_type' => 'deployment',
                    'risk_level' => 'high',
                    'description' => 'Deploy to production',
                    'evidence' => ['diff' => '+100 -2'],
                ]);

                return ['deployed' => ($decision['status'] ?? null) === 'approved'];
            }))
            ->setEntryPoint('deploy')
            ->addEdge('deploy', Constants::END)
            ->compile();

        // First pass suspends; bridge records a pending Approval.
        $first = (new GraphRunner(new DatabaseCheckpointSaver))->run($graph, [], new GraphContext((string) $run->id));
        $this->assertTrue($first->isInterrupted());

        $approval = $bridge->record($run, $first->interrupt);
        $this->assertTrue($bridge->hasPending($run));
        $this->assertDatabaseHas('bossku_ai_approvals', [
            'id' => $approval->id,
            'run_id' => $run->id,
            'operation_type' => 'deployment',
            'risk_level' => 'high',
            'status' => 'pending',
        ]);
        $this->assertSame('approve:deploy', $approval->metadata['interrupt_key']);

        // Human approves via the normal approval flow.
        $approval->update(['status' => 'approved', 'decision_note' => 'lgtm', 'decided_by' => 'tester']);
        $this->assertFalse($bridge->hasPending($run->refresh()));

        // Resume with the decision injected from the approval.
        $scratch = $bridge->resumeScratch($run);
        $this->assertArrayHasKey('approve:deploy', $scratch);
        $resumeCtx = new GraphContext((string) $run->id, scratch: $scratch);
        $second = (new GraphRunner(new DatabaseCheckpointSaver))->resume($graph, $resumeCtx);

        $this->assertTrue($second->isCompleted());
        $this->assertTrue($second->values['deployed']);
    }

    #[Test]
    public function rejected_approval_resumes_with_a_negative_decision(): void
    {
        $run = Run::query()->create(['prompt' => 'ship it', 'status' => 'running']);
        $bridge = new ApprovalInterruptBridge;

        $graph = (new GraphBuilder(StateSchema::make(['deployed' => new LastValue])))
            ->addNode('deploy', new CallableNode(function (RunState $s, GraphContext $c): array {
                $decision = $c->interrupt('approve:deploy', ['operation_type' => 'deployment']);

                return ['deployed' => ($decision['status'] ?? null) === 'approved'];
            }))
            ->setEntryPoint('deploy')
            ->addEdge('deploy', Constants::END)
            ->compile();

        $first = (new GraphRunner(new DatabaseCheckpointSaver))->run($graph, [], new GraphContext((string) $run->id));
        $approval = $bridge->record($run, $first->interrupt);
        $approval->update(['status' => 'rejected', 'decided_by' => 'tester']);

        $second = (new GraphRunner(new DatabaseCheckpointSaver))->resume(
            $graph,
            new GraphContext((string) $run->id, scratch: $bridge->resumeScratch($run)),
        );

        $this->assertTrue($second->isCompleted());
        $this->assertFalse($second->values['deployed']);
    }
}
