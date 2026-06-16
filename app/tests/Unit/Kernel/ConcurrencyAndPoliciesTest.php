<?php

namespace Tests\Unit\Kernel;

use App\Services\Kernel\Cache\InMemoryCacheStore;
use App\Services\Kernel\Channels\LastValue;
use App\Services\Kernel\Channels\Topic;
use App\Services\Kernel\Graph\GraphBuilder;
use App\Services\Kernel\Graph\StateSchema;
use App\Services\Kernel\Nodes\CallableNode;
use App\Services\Kernel\Nodes\SubgraphNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Types\CachePolicy;
use App\Services\Kernel\Types\RetryPolicy;
use App\Services\Kernel\Types\Command;
use App\Services\Kernel\Types\Send;
use App\Services\Kernel\Types\TimeoutPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConcurrencyAndPoliciesTest extends TestCase
{
    #[Test]
    public function send_fans_out_to_workers_and_joins_at_a_reduce_node(): void
    {
        $schema = StateSchema::make(['results' => new Topic, 'total' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('map', new CallableNode(fn (): Command => new Command(send: [
                new Send('square', ['n' => 1]),
                new Send('square', ['n' => 2]),
                new Send('square', ['n' => 3]),
            ])))
            ->addNode('square', new CallableNode(function (RunState $s, GraphContext $c): array {
                $n = (int) $c->input('n');

                return ['results' => $n * $n];
            }))
            ->addNode('reduce', new CallableNode(fn (RunState $s): array => ['total' => array_sum($s->get('results', []))]))
            ->setEntryPoint('map')
            ->addEdge('square', 'reduce')
            ->addEdge('reduce', Constants::END)
            ->compile();

        $result = (new GraphRunner)->run($graph, [], new GraphContext('t-send'));

        $this->assertTrue($result->isCompleted());
        $this->assertSame([1, 4, 9], $result->values['results']);
        $this->assertSame(14, $result->values['total']);
    }

    #[Test]
    public function subgraph_runs_a_child_graph_as_a_node(): void
    {
        $child = (new GraphBuilder(StateSchema::make(['x' => new LastValue, 'y' => new LastValue])))
            ->addNode('double', new CallableNode(fn (RunState $s): array => ['y' => (int) $s->get('x') * 2]))
            ->setEntryPoint('double')
            ->addEdge('double', Constants::END)
            ->compile();

        $parent = (new GraphBuilder(StateSchema::make(['x' => new LastValue, 'y' => new LastValue, 'z' => new LastValue])))
            ->addNode('sub', new SubgraphNode($child, inputKeys: ['x'], outputKeys: ['y']))
            ->addNode('after', new CallableNode(fn (RunState $s): array => ['z' => (int) $s->get('y') + 1]))
            ->setEntryPoint('sub')
            ->addEdge('sub', 'after')
            ->addEdge('after', Constants::END)
            ->compile();

        $result = (new GraphRunner)->run($parent, ['x' => 5], new GraphContext('t-sub'));

        $this->assertSame(10, $result->values['y']);
        $this->assertSame(11, $result->values['z']);
    }

    #[Test]
    public function retry_policy_reattempts_a_flaky_node(): void
    {
        $attempts = 0;
        $graph = (new GraphBuilder(StateSchema::make(['ok' => new LastValue])))
            ->addNode('flaky', new CallableNode(function () use (&$attempts): array {
                $attempts++;
                if ($attempts < 3) {
                    throw new RuntimeException('transient');
                }

                return ['ok' => true];
            }), new RetryPolicy(maxAttempts: 3))
            ->setEntryPoint('flaky')
            ->addEdge('flaky', Constants::END)
            ->compile();

        $result = (new GraphRunner)->run($graph, [], new GraphContext('t-retry'));

        $this->assertSame(3, $attempts);
        $this->assertTrue($result->values['ok']);
    }

    #[Test]
    public function retry_policy_rethrows_after_exhausting_attempts(): void
    {
        $graph = (new GraphBuilder(StateSchema::make(['ok' => new LastValue])))
            ->addNode('always', new CallableNode(function (): array {
                throw new RuntimeException('permanent');
            }), new RetryPolicy(maxAttempts: 2))
            ->setEntryPoint('always')
            ->addEdge('always', Constants::END)
            ->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('permanent');
        (new GraphRunner)->run($graph, [], new GraphContext('t-retry-fail'));
    }

    #[Test]
    public function cache_policy_skips_recomputation_on_a_hit(): void
    {
        $calls = 0;
        $schema = StateSchema::make(['seed' => new LastValue, 'v' => new LastValue]);
        $graph = (new GraphBuilder($schema))
            ->addNode('compute', new CallableNode(function () use (&$calls): array {
                $calls++;

                return ['v' => $calls];
            }), cache: new CachePolicy)
            ->setEntryPoint('compute')
            ->addEdge('compute', Constants::END)
            ->compile();

        $store = new InMemoryCacheStore;
        $runner = new GraphRunner(null, $store);

        $first = $runner->run($graph, ['seed' => 'k'], new GraphContext('t-cache-1'));
        $second = $runner->run($graph, ['seed' => 'k'], new GraphContext('t-cache-2'));

        $this->assertSame(1, $calls); // computed once, second run hit the cache
        $this->assertSame(1, $first->values['v']);
        $this->assertSame(1, $second->values['v']);
    }

    #[Test]
    public function timeout_policy_fails_a_slow_node(): void
    {
        $graph = (new GraphBuilder(StateSchema::make(['x' => new LastValue])))
            ->addNode('slow', new CallableNode(function (): array {
                usleep(5000); // 5ms

                return ['x' => 1];
            }), timeout: new TimeoutPolicy(0.001)) // 1ms budget
            ->setEntryPoint('slow')
            ->addEdge('slow', Constants::END)
            ->compile();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/timeout/i');
        (new GraphRunner)->run($graph, [], new GraphContext('t-timeout'));
    }
}
