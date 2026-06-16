<?php

namespace Tests\Unit\Kernel;

use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Checkpoint\InMemoryCheckpointSaver;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Runtime\StreamMode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InterruptTest extends TestCase
{
    #[Test]
    public function dynamic_interrupt_helper_throws_then_returns_on_resume(): void
    {
        $seen = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('gate', new CallableNode(function (RunState $s, GraphContext $c) use (&$seen): array {
                $decision = $c->interrupt('approve', ['risk_level' => 'high']);
                $seen[] = $decision;

                return ['x' => $decision];
            }))
            ->setEntryPoint('gate')
            ->addEdge('gate', Constants::END)
            ->compile($saver = new InMemoryCheckpointSaver);

        $runner = new GraphRunner($saver);

        $first = $runner->run($graph, [], new GraphContext('t-dyn'));
        $this->assertTrue($first->isInterrupted());
        $this->assertSame('approve', $first->interrupt->value['key']);
        $this->assertSame(['risk_level' => 'high'], $first->interrupt->value['request']);
        $this->assertSame([], $seen);

        $second = $runner->resume($graph, new GraphContext('t-dyn', scratch: ['approve' => 'yes']));
        $this->assertTrue($second->isCompleted());
        $this->assertSame(['yes'], $seen);
        $this->assertSame('yes', $second->values['x']);
    }

    #[Test]
    public function interrupt_before_suspends_until_resume(): void
    {
        $log = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = $this->twoStep($log, before: 'risky');

        $runner = new GraphRunner($saver = new InMemoryCheckpointSaver);
        $first = $runner->run($graph, [], new GraphContext('t-before'));

        $this->assertTrue($first->isInterrupted());
        $this->assertSame(['safe'], $log); // risky did NOT run yet

        $second = $runner->resume($graph, new GraphContext('t-before'));
        $this->assertTrue($second->isCompleted());
        $this->assertSame(['safe', 'risky'], $log);
    }

    #[Test]
    public function interrupt_after_suspends_before_successor(): void
    {
        $log = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = $this->twoStep($log, after: 'safe');

        $runner = new GraphRunner($saver = new InMemoryCheckpointSaver);
        $first = $runner->run($graph, [], new GraphContext('t-after'));

        $this->assertTrue($first->isInterrupted());
        $this->assertSame(['safe'], $log); // ran safe, paused before risky

        $second = $runner->resume($graph, new GraphContext('t-after'));
        $this->assertTrue($second->isCompleted());
        $this->assertSame(['safe', 'risky'], $log);
    }

    #[Test]
    public function runner_emits_tagged_stream_modes(): void
    {
        $modes = [];
        $schema = StateSchema::make(['x' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('a', new CallableNode(fn (): array => ['x' => 1]))
            ->setEntryPoint('a')
            ->addEdge('a', Constants::END)
            ->compile($saver = new InMemoryCheckpointSaver);

        $emit = function (array $evt) use (&$modes): void {
            $modes[] = $evt['mode'] ?? null;
        };
        (new GraphRunner($saver))->run($graph, [], new GraphContext('t-modes', emit: $emit));

        $this->assertContains(StreamMode::TASKS, $modes);
        $this->assertContains(StreamMode::UPDATES, $modes);
        $this->assertContains(StreamMode::CHECKPOINTS, $modes);
        $this->assertContains(StreamMode::VALUES, $modes);
    }

    private function twoStep(?array &$log, ?string $before = null, ?string $after = null): \App\Services\Kernel\Graph\CompiledGraph
    {
        $log = [];
        $builder = (new GraphBuilder(StateSchema::make(['x' => new LastValue])))
            ->addNode('safe', new CallableNode(function () use (&$log): array {
                $log[] = 'safe';

                return [];
            }))
            ->addNode('risky', new CallableNode(function () use (&$log): array {
                $log[] = 'risky';

                return [];
            }))
            ->setEntryPoint('safe')
            ->addEdge('safe', 'risky')
            ->addEdge('risky', Constants::END);

        if ($before !== null) {
            $builder->interruptBefore($before);
        }
        if ($after !== null) {
            $builder->interruptAfter($after);
        }

        return $builder->compile();
    }
}
