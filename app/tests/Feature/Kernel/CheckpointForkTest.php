<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Channels\Topic;
use App\Services\Kernel\Checkpoint\CheckpointService;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\CompiledGraph;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckpointForkTest extends TestCase
{
    use RefreshDatabase;

    private function schema(): StateSchema
    {
        return StateSchema::make(['x' => new LastValue, 'log' => new Topic]);
    }

    private function graph(): CompiledGraph
    {
        return (new GraphBuilder($this->schema()))
            ->addNode('a', new CallableNode(fn (): array => ['x' => 'a-val', 'log' => 'a']))
            ->addNode('b', new CallableNode(fn (RunState $s): array => ['log' => 'b:'.$s->get('x')]))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')
            ->addEdge('b', Constants::END)
            ->compile();
    }

    #[Test]
    public function fork_from_a_checkpoint_branches_a_new_run_with_patched_state(): void
    {
        $run = Run::query()->create(['prompt' => 'original', 'status' => 'running']);
        $saver = new DatabaseCheckpointSaver;

        (new GraphRunner($saver))->run($this->graph(), [], new GraphContext((string) $run->id));

        // The checkpoint written after node "a" (frontier == [b]).
        $afterA = collect($saver->list((string) $run->id, 100))
            ->first(fn ($cp) => $cp->next === ['b']);
        $this->assertNotNull($afterA);

        $service = new CheckpointService($saver);
        $fork = $service->fork($run, $afterA->id, ['x' => 'forked'], $this->schema());

        $this->assertSame('fork', $fork->run_kind);
        $this->assertSame((string) $run->id, (string) $fork->parent_run_id);
        $this->assertDatabaseHas('bossku_ai_checkpoints', [
            'thread_id' => $fork->id,
            'source' => 'fork',
        ]);

        // Resume the fork: node "b" runs against the PATCHED state, not the original.
        $result = (new GraphRunner($saver))->resume($this->graph(), new GraphContext((string) $fork->id));
        $this->assertTrue($result->isCompleted());
        $this->assertSame('forked', $result->values['x']);
        $this->assertSame(['a', 'b:forked'], $result->values['log']);
    }
}
