<?php

namespace Tests\Feature\Kernel;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\CompiledGraph;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\GraphInterrupt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseCheckpointSaverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function checkpoints_persist_to_the_database_per_superstep(): void
    {
        $run = Run::query()->create(['prompt' => 'hi', 'status' => 'running']);
        $saver = new DatabaseCheckpointSaver;

        $result = (new GraphRunner($saver))->run(
            $this->linearGraph($log),
            ['x' => 'seed'],
            new GraphContext((string) $run->id),
        );

        $this->assertSame(Constants::STATUS_COMPLETED, $result->status);
        $this->assertSame(['a', 'b'], $log);
        // input checkpoint + 2 supersteps
        $this->assertDatabaseCount('bossku_ai_checkpoints', 3);

        $latest = $saver->latest((string) $run->id);
        $this->assertNotNull($latest);
        $this->assertSame([], $latest->next); // run is complete: empty frontier
        $this->assertSame('seed', $latest->channelValues['x']['v'] ?? null);
    }

    #[Test]
    public function a_crashed_run_resumes_from_the_database_checkpoint(): void
    {
        $run = Run::query()->create(['prompt' => 'do work', 'status' => 'running']);
        $threadId = (string) $run->id;

        // Build a graph whose node "b" interrupts until a human value is present.
        $graph = fn (?array &$log): CompiledGraph => (new GraphBuilder(StateSchema::make(['x' => new LastValue])))
            ->addNode('a', new CallableNode(function () use (&$log): array {
                $log[] = 'a';

                return ['x' => 'from-a'];
            }))
            ->addNode('b', new CallableNode(function (RunState $s, GraphContext $c) use (&$log) {
                if (! $c->hasResume('b')) {
                    throw new GraphInterrupt('approve deploy?');
                }
                $log[] = 'b';

                return [];
            }))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')
            ->addEdge('b', Constants::END)
            ->compile();

        // First "process": suspends at b and persists the interrupt checkpoint.
        $logA = [];
        $first = (new GraphRunner(new DatabaseCheckpointSaver))->run($graph($logA), [], new GraphContext($threadId));
        $this->assertSame(Constants::STATUS_INTERRUPTED, $first->status);
        $this->assertSame(['a'], $logA);
        $this->assertDatabaseHas('bossku_ai_checkpoints', ['thread_id' => $threadId, 'source' => 'interrupt']);

        // Second "process": brand-new saver + runner reload state from the DB and finish.
        $logB = [];
        $resumeCtx = new GraphContext($threadId, scratch: ['b' => 'yes']);
        $second = (new GraphRunner(new DatabaseCheckpointSaver))->resume($graph($logB), $resumeCtx);

        $this->assertSame(Constants::STATUS_COMPLETED, $second->status);
        $this->assertSame(['b'], $logB);
        // State written by "a" survived the restart and was visible on resume.
        $this->assertSame('from-a', $second->values['x']);
    }

    private function linearGraph(?array &$log): CompiledGraph
    {
        $log = [];

        return (new GraphBuilder(StateSchema::make(['x' => new LastValue])))
            ->addNode('a', new CallableNode(function () use (&$log): array {
                $log[] = 'a';

                return [];
            }))
            ->addNode('b', new CallableNode(function () use (&$log): array {
                $log[] = 'b';

                return [];
            }))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')
            ->addEdge('b', Constants::END)
            ->compile();
    }
}
