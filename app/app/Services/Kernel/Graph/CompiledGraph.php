<?php

namespace App\Services\Kernel\Graph;

use App\Services\Kernel\Checkpoint\CheckpointSaverInterface;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\CachePolicy;
use App\Services\Kernel\Types\RetryPolicy;
use App\Services\Kernel\Types\TimeoutPolicy;
use RuntimeException;

/**
 * A frozen, executable graph. Immutable data describing nodes, static edges,
 * conditional branches, the state schema, and an optional checkpointer. The
 * GraphRunner consumes this; it holds no mutable run state itself.
 */
final class CompiledGraph
{
    /**
     * @param  array<string, NodeInterface>  $nodes
     * @param  array<string, list<string>>  $edges
     * @param  array<string, array{router: callable, mapping: array<string, string>}>  $branches
     * @param  array<string, true>  $interruptBefore
     * @param  array<string, true>  $interruptAfter
     * @param  array<string, array{retry?: RetryPolicy, timeout?: TimeoutPolicy, cache?: CachePolicy}>  $policies
     */
    public function __construct(
        private readonly StateSchema $schema,
        private readonly array $nodes,
        private readonly array $edges,
        private readonly array $branches,
        private readonly ?CheckpointSaverInterface $saver = null,
        private readonly array $interruptBefore = [],
        private readonly array $interruptAfter = [],
        private readonly array $policies = [],
    ) {}

    public function retryPolicy(string $node): ?RetryPolicy
    {
        return $this->policies[$node]['retry'] ?? null;
    }

    public function timeoutPolicy(string $node): ?TimeoutPolicy
    {
        return $this->policies[$node]['timeout'] ?? null;
    }

    public function cachePolicy(string $node): ?CachePolicy
    {
        return $this->policies[$node]['cache'] ?? null;
    }

    public function shouldInterruptBefore(string $node): bool
    {
        return isset($this->interruptBefore[$node]);
    }

    public function shouldInterruptAfter(string $node): bool
    {
        return isset($this->interruptAfter[$node]);
    }

    /** @param list<string> $nodes */
    public function anyInterruptBefore(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($this->shouldInterruptBefore($node)) {
                return true;
            }
        }

        return false;
    }

    public function schema(): StateSchema
    {
        return $this->schema;
    }

    public function saver(): ?CheckpointSaverInterface
    {
        return $this->saver;
    }

    public function node(string $name): NodeInterface
    {
        if (! isset($this->nodes[$name])) {
            throw new RuntimeException("Unknown node: {$name}");
        }

        return $this->nodes[$name];
    }

    public function hasNode(string $name): bool
    {
        return isset($this->nodes[$name]);
    }

    /** @return list<string> entry node names (edges from START) */
    public function entryPoints(): array
    {
        return $this->edges[Constants::START] ?? [];
    }

    /**
     * Successor node names for $node given the current state. Resolves a
     * conditional branch if present, else static edges, else END.
     *
     * @return list<string>
     */
    public function successors(string $node, RunState $state): array
    {
        if (isset($this->branches[$node])) {
            $branch = $this->branches[$node];
            $key = ($branch['router'])($state);
            if (! array_key_exists($key, $branch['mapping'])) {
                throw new RuntimeException("Conditional edge from '{$node}' returned unmapped key '{$key}'.");
            }

            return [$branch['mapping'][$key]];
        }

        return $this->edges[$node] ?? [Constants::END];
    }
}
