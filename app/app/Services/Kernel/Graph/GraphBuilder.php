<?php

namespace App\Services\Kernel\Graph;

use App\Services\Kernel\Checkpoint\CheckpointSaverInterface;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\CachePolicy;
use App\Services\Kernel\Types\RetryPolicy;
use App\Services\Kernel\Types\TimeoutPolicy;
use InvalidArgumentException;

/**
 * Fluent builder for an execution graph (LangGraph's StateGraph). Nodes are
 * agents/tools; edges define static routing; conditional edges route on a
 * predicate over state. compile() validates the graph and freezes it.
 */
final class GraphBuilder
{
    /** @var array<string, NodeInterface> */
    private array $nodes = [];

    /** @var array<string, list<string>> static edges: from => [to, ...] */
    private array $edges = [];

    /** @var array<string, array{router: callable, mapping: array<string, string>}> */
    private array $branches = [];

    /** @var array<string, true> nodes that suspend the run before executing */
    private array $interruptBefore = [];

    /** @var array<string, true> nodes that suspend the run after executing */
    private array $interruptAfter = [];

    /** @var array<string, array{retry?: RetryPolicy, timeout?: TimeoutPolicy, cache?: CachePolicy}> */
    private array $policies = [];

    public function __construct(private readonly StateSchema $schema) {}

    public function addNode(
        string $name,
        NodeInterface $node,
        ?RetryPolicy $retry = null,
        ?TimeoutPolicy $timeout = null,
        ?CachePolicy $cache = null,
    ): self {
        if ($name === Constants::START || $name === Constants::END) {
            throw new InvalidArgumentException("Reserved node name: {$name}");
        }
        if (isset($this->nodes[$name])) {
            throw new InvalidArgumentException("Duplicate node: {$name}");
        }
        $this->nodes[$name] = $node;

        $policy = [];
        if ($retry !== null) {
            $policy['retry'] = $retry;
        }
        if ($timeout !== null) {
            $policy['timeout'] = $timeout;
        }
        if ($cache !== null) {
            $policy['cache'] = $cache;
        }
        if ($policy !== []) {
            $this->policies[$name] = $policy;
        }

        return $this;
    }

    public function addEdge(string $from, string $to): self
    {
        $this->edges[$from][] = $to;

        return $this;
    }

    /**
     * Route from $from to a node chosen at runtime: $router(RunState) returns a
     * key, looked up in $mapping to a node name or END.
     *
     * @param  callable(RunState): string  $router
     * @param  array<string, string>  $mapping
     */
    public function addConditionalEdges(string $from, callable $router, array $mapping): self
    {
        $this->branches[$from] = ['router' => $router, 'mapping' => $mapping];

        return $this;
    }

    /** Suspend the run before each named node runs (static human-in-the-loop gate). */
    public function interruptBefore(string ...$nodes): self
    {
        foreach ($nodes as $node) {
            $this->interruptBefore[$node] = true;
        }

        return $this;
    }

    /** Suspend the run after each named node runs. */
    public function interruptAfter(string ...$nodes): self
    {
        foreach ($nodes as $node) {
            $this->interruptAfter[$node] = true;
        }

        return $this;
    }

    public function setEntryPoint(string $name): self
    {
        return $this->addEdge(Constants::START, $name);
    }

    public function setFinishPoint(string $name): self
    {
        return $this->addEdge($name, Constants::END);
    }

    public function compile(?CheckpointSaverInterface $saver = null): CompiledGraph
    {
        $entries = $this->edges[Constants::START] ?? [];
        if ($entries === []) {
            throw new InvalidArgumentException('Graph has no entry point (add an edge from START).');
        }

        foreach ($this->validTargets() as $target => $sourceLabel) {
            if ($target === Constants::END || isset($this->nodes[$target])) {
                continue;
            }
            throw new InvalidArgumentException("Edge target '{$target}' ({$sourceLabel}) is not a registered node.");
        }

        return new CompiledGraph(
            $this->schema,
            $this->nodes,
            $this->edges,
            $this->branches,
            $saver,
            $this->interruptBefore,
            $this->interruptAfter,
            $this->policies,
        );
    }

    /**
     * @return array<string, string> target node name => human label of its source
     */
    private function validTargets(): array
    {
        $targets = [];
        foreach ($this->edges as $from => $tos) {
            foreach ($tos as $to) {
                if ($from !== Constants::START) {
                    $targets[$to] = "edge from {$from}";
                } elseif (! isset($targets[$to])) {
                    $targets[$to] = 'entry point';
                }
            }
        }
        foreach ($this->branches as $from => $branch) {
            foreach ($branch['mapping'] as $key => $to) {
                $targets[$to] = "conditional edge from {$from} (key '{$key}')";
            }
        }

        return $targets;
    }
}
