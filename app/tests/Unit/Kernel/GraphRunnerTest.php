<?php

namespace Tests\Unit\Kernel;

use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Checkpoint\InMemoryCheckpointSaver;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\DefaultPipelineGraph;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Kernel\Types\GraphInterrupt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GraphRunnerTest extends TestCase
{
    /**
     * The default pipeline graph must run exactly the stages named by the
     * classifier workflow string — matching the legacy workflow matrix.
     *
     * @param  list<string>  $expected
     */
    #[DataProvider('workflowProvider')]
    #[Test]
    public function default_pipeline_routes_match_the_workflow_matrix(string $workflow, array $expected): void
    {
        $log = [];
        $nodes = [];
        foreach (DefaultPipelineGraph::nodeNames() as $name) {
            $nodes[$name] = new CallableNode(function (RunState $s, GraphContext $c) use (&$log, $name): array {
                $log[] = $name;

                return [];
            });
        }

        $graph = DefaultPipelineGraph::build($nodes);
        $runner = new GraphRunner;
        $result = $runner->run($graph, ['route' => ['workflow' => $workflow]], new GraphContext('t-'.$workflow));

        $this->assertSame(Constants::STATUS_COMPLETED, $result->status);
        $this->assertSame($expected, $log);
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function workflowProvider(): array
    {
        $base = ['router', 'memory', 'planner', 'executor'];

        return [
            'plan + execute' => ['orchestrator_executor', $base],
            'with auditor' => ['orchestrator_executor_auditor', [...$base, 'auditor']],
            'with security' => ['orchestrator_executor_auditor_security', [...$base, 'auditor', 'security']],
            'full chain' => ['orchestrator_executor_auditor_security_final_reviewer', [...$base, 'auditor', 'security', 'final']],
        ];
    }

    #[Test]
    public function command_goto_overrides_static_edges(): void
    {
        $log = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('a', new CallableNode(function () use (&$log): Command {
                $log[] = 'a';

                return new Command(update: ['x' => 1], goto: 'c');
            }))
            ->addNode('b', new CallableNode(function () use (&$log): array {
                $log[] = 'b';

                return [];
            }))
            ->addNode('c', new CallableNode(function () use (&$log): array {
                $log[] = 'c';

                return [];
            }))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')   // static edge, overridden by Command(goto: c)
            ->addEdge('b', Constants::END)
            ->addEdge('c', Constants::END)
            ->compile();

        $result = (new GraphRunner)->run($graph, [], new GraphContext('t1'));

        $this->assertSame(['a', 'c'], $log);
        $this->assertSame(1, $result->values['x']);
    }

    #[Test]
    public function checkpoints_are_written_each_superstep(): void
    {
        $saver = new InMemoryCheckpointSaver;
        $graph = $this->linearGraph($log);

        (new GraphRunner($saver))->run($graph, [], new GraphContext('thread-x'));

        // input checkpoint + one per superstep (a, b, c)
        $this->assertCount(4, $saver->list('thread-x', 100));
        $this->assertSame(['a', 'b', 'c'], $log);
    }

    #[Test]
    public function run_resumes_from_an_interrupt_with_human_input(): void
    {
        $log = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('a', new CallableNode(function () use (&$log): array {
                $log[] = 'a';

                return [];
            }))
            ->addNode('b', new CallableNode(function (RunState $s, GraphContext $c) use (&$log) {
                if (! $c->hasResume('b')) {
                    throw new GraphInterrupt('need approval');
                }
                $log[] = 'b:'.$c->resumeValue('b');

                return [];
            }))
            ->addNode('c', new CallableNode(function () use (&$log): array {
                $log[] = 'c';

                return [];
            }))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')
            ->addEdge('b', 'c')
            ->addEdge('c', Constants::END)
            ->compile($saver = new InMemoryCheckpointSaver);

        $runner = new GraphRunner($saver);

        // First pass suspends at b.
        $first = $runner->run($graph, [], new GraphContext('thread-i'));
        $this->assertSame(Constants::STATUS_INTERRUPTED, $first->status);
        $this->assertNotNull($first->interrupt);
        $this->assertSame('b', $first->interrupt->node);
        $this->assertSame('need approval', $first->interrupt->value);
        $this->assertSame(['a'], $log);

        // Resume (as a fresh "process") with the human input injected.
        $resumeCtx = new GraphContext('thread-i', scratch: ['b' => 'go']);
        $second = (new GraphRunner($saver))->resume($graph, $resumeCtx);

        $this->assertSame(Constants::STATUS_COMPLETED, $second->status);
        $this->assertSame(['a', 'b:go', 'c'], $log);
    }

    /**
     * Linear a → b → c graph that records execution order into $log.
     */
    private function linearGraph(?array &$log): \App\Services\Kernel\Graph\CompiledGraph
    {
        $log = [];
        $schema = StateSchema::make(['x' => new LastValue]);

        return (new GraphBuilder($schema))
            ->addNode('a', new CallableNode(function () use (&$log): array {
                $log[] = 'a';

                return [];
            }))
            ->addNode('b', new CallableNode(function () use (&$log): array {
                $log[] = 'b';

                return [];
            }))
            ->addNode('c', new CallableNode(function () use (&$log): array {
                $log[] = 'c';

                return [];
            }))
            ->setEntryPoint('a')
            ->addEdge('a', 'b')
            ->addEdge('b', 'c')
            ->addEdge('c', Constants::END)
            ->compile();
    }
}
