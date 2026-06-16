<?php

namespace App\Services\Kernel\Runtime;

use App\Services\Kernel\Cache\CacheStoreInterface;
use App\Services\Kernel\Checkpoint\Checkpoint;
use App\Services\Kernel\Checkpoint\CheckpointSaverInterface;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\CompiledGraph;
use App\Services\Kernel\Types\Command;
use App\Services\Kernel\Types\GraphInterrupt;
use App\Services\Kernel\Types\Interrupt;
use Throwable;

/**
 * The scheduler — a Pregel/BSP superstep loop. Each superstep: optionally gate
 * on a static interrupt, run the active frontier of tasks, apply their writes to
 * the shared state via reducers, compute the next frontier from
 * edges/branches/Commands/Sends, then checkpoint the full state. A crashed or
 * suspended run resumes from its latest checkpoint.
 *
 * Phase 1 executes the frontier sequentially; because writes merge through
 * channel reducers, the semantics are forward-compatible with parallel node
 * execution. Phase 2 adds durable interrupts and stream modes. Phase 3 adds
 * Send fan-out/fan-in (task payloads) and per-node retry/timeout/cache policies.
 */
final class GraphRunner
{
    public function __construct(
        private readonly ?CheckpointSaverInterface $saver = null,
        private readonly ?CacheStoreInterface $cacheStore = null,
    ) {}

    /**
     * Start a fresh run.
     *
     * @param array<string, mixed> $input initial channel writes
     */
    public function run(CompiledGraph $graph, array $input, GraphContext $ctx, int $maxSteps = 100): GraphResult
    {
        $state = RunState::fromSchema($graph->schema());
        $state->update($input);

        $frontier = array_map(static fn (string $n): PregelTask => new PregelTask($n), $graph->entryPoints());
        $checkpointId = $this->checkpoint($ctx, $state, $frontier, 0, Constants::SOURCE_INPUT, null);

        return $this->loop($graph, $ctx, $state, $frontier, 0, $checkpointId, $maxSteps, false);
    }

    /**
     * Resume a run from its latest checkpoint (after a crash, or after a human
     * supplied resume input following an interrupt). The first superstep bypasses
     * static interrupt-before gates so the run advances past the gate it paused on.
     *
     * @param array<string, mixed> $resumeWrites channel writes injected before continuing
     */
    public function resume(CompiledGraph $graph, GraphContext $ctx, array $resumeWrites = [], int $maxSteps = 100): GraphResult
    {
        $saver = $this->saver;
        if ($saver === null) {
            throw new \RuntimeException('Cannot resume without a checkpoint saver.');
        }
        $cp = $saver->latest($ctx->threadId);
        if ($cp === null) {
            throw new \RuntimeException("No checkpoint found for thread {$ctx->threadId}.");
        }

        $state = RunState::fromSchema($graph->schema());
        $state->restore($cp->channelValues);
        if ($resumeWrites !== []) {
            $state->update($resumeWrites);
        }

        $frontier = array_map(static fn ($v): PregelTask => PregelTask::fromMixed($v), $cp->next);

        return $this->loop($graph, $ctx, $state, $frontier, $cp->step, $cp->id, $maxSteps, true);
    }

    /**
     * @param list<PregelTask> $frontier
     */
    private function loop(
        CompiledGraph $graph,
        GraphContext $ctx,
        RunState $state,
        array $frontier,
        int $step,
        ?string $parentId,
        int $maxSteps,
        bool $bypassBefore,
    ): GraphResult {
        $frontier = $this->normalize($frontier);

        while ($frontier !== []) {
            $nodes = $this->nodeNames($frontier);

            // Static interrupt-before: suspend this superstep's frontier so a
            // human can inspect/approve before the node runs. Bypassed once on
            // resume so the gate doesn't immediately re-trigger.
            if (! $bypassBefore && $graph->anyInterruptBefore($nodes)) {
                $node = $this->firstInterruptBefore($graph, $nodes);
                $cpId = $this->checkpoint($ctx, $state, $frontier, $step, Constants::SOURCE_INTERRUPT, $parentId);
                $payload = new Interrupt(Checkpoint::newId(), $node, ['static' => 'before', 'node' => $node]);
                $ctx->emit($this->event(StreamMode::DEBUG, ['type' => 'kernel_interrupt', 'mode_detail' => 'static_before', 'node' => $node, 'step' => $step]));

                return new GraphResult(Constants::STATUS_INTERRUPTED, $state->values(), $payload, $cpId, $step);
            }
            $bypassBefore = false;

            if ($step >= $maxSteps) {
                return new GraphResult(Constants::STATUS_MAX_STEPS, $state->values(), null, $parentId, $step);
            }
            $step++;
            $ctx->step = $step;

            /** @var list<array{0: PregelTask, 1: array<string,mixed>|Command}> $results */
            $results = [];
            foreach ($frontier as $task) {
                $ctx->emit($this->event(StreamMode::TASKS, ['type' => 'kernel_task', 'event' => 'start', 'node' => $task->node, 'step' => $step]));
                try {
                    $output = $this->invokeNode($graph, $ctx, $state, $task);
                } catch (GraphInterrupt $interrupt) {
                    // Freeze at the start of this superstep so resume re-runs the
                    // same frontier with the human input injected.
                    $cpId = $this->checkpoint($ctx, $state, $frontier, $step - 1, Constants::SOURCE_INTERRUPT, $parentId);
                    $payload = new Interrupt(Checkpoint::newId(), $task->node, $interrupt->value);
                    $ctx->emit($this->event(StreamMode::DEBUG, ['type' => 'kernel_interrupt', 'mode_detail' => 'dynamic', 'node' => $task->node, 'step' => $step, 'value' => $interrupt->value]));

                    return new GraphResult(Constants::STATUS_INTERRUPTED, $state->values(), $payload, $cpId, $step);
                }
                $results[] = [$task, $output];
            }

            /** @var array<string, PregelTask> $next */
            $next = [];
            $interruptAfter = false;
            foreach ($results as [$task, $output]) {
                $node = $task->node;
                $tasks = [];
                if ($output instanceof Command) {
                    if ($output->update !== []) {
                        $state->update($output->update);
                    }
                    foreach ($output->send as $send) {
                        $tasks[] = new PregelTask($send->node, $send->state);
                    }
                    if ($output->hasGoto()) {
                        foreach ($output->gotoList() as $target) {
                            $tasks[] = new PregelTask($target);
                        }
                    }
                    if (! $output->hasGoto() && $output->send === []) {
                        foreach ($graph->successors($node, $state) as $target) {
                            $tasks[] = new PregelTask($target);
                        }
                    }
                    $delta = $output->update;
                } else {
                    $state->update($output);
                    foreach ($graph->successors($node, $state) as $target) {
                        $tasks[] = new PregelTask($target);
                    }
                    $delta = $output;
                }
                foreach ($tasks as $t) {
                    if ($t->node !== Constants::END) {
                        $next[$t->signature()] = $t;
                    }
                }
                $ctx->emit($this->event(StreamMode::UPDATES, ['type' => 'kernel_update', 'node' => $node, 'step' => $step, 'keys' => array_keys($delta)]));
                if ($graph->shouldInterruptAfter($node)) {
                    $interruptAfter = true;
                }
            }

            $state->consumeTransient();
            $frontier = array_values($next);

            // Static interrupt-after: suspend before running the successors.
            $source = $interruptAfter && $frontier !== [] ? Constants::SOURCE_INTERRUPT : Constants::SOURCE_LOOP;
            $parentId = $this->checkpoint($ctx, $state, $frontier, $step, $source, $parentId);

            if ($interruptAfter && $frontier !== []) {
                $payload = new Interrupt(Checkpoint::newId(), implode(',', $this->nodeNames($this->normalize($frontier))), ['static' => 'after']);
                $ctx->emit($this->event(StreamMode::DEBUG, ['type' => 'kernel_interrupt', 'mode_detail' => 'static_after', 'step' => $step]));

                return new GraphResult(Constants::STATUS_INTERRUPTED, $state->values(), $payload, $parentId, $step);
            }
        }

        $ctx->emit($this->event(StreamMode::VALUES, ['type' => 'kernel_values', 'step' => $step, 'values' => $state->values()]));

        return new GraphResult(Constants::STATUS_COMPLETED, $state->values(), null, $parentId, $step);
    }

    /**
     * Invoke one task with per-node cache, retry, and timeout policies applied.
     * GraphInterrupt always propagates (never cached or retried).
     *
     * @return array<string, mixed>|Command
     */
    private function invokeNode(CompiledGraph $graph, GraphContext $ctx, RunState $state, PregelTask $task): array|Command
    {
        $node = $task->node;

        $cache = $graph->cachePolicy($node);
        $cacheKey = null;
        if ($cache !== null && $this->cacheStore !== null) {
            $cacheKey = $cache->key($node, $state);
            $hit = $this->cacheStore->get($cacheKey);
            if ($hit !== null) {
                $ctx->emit($this->event(StreamMode::DEBUG, ['type' => 'kernel_cache_hit', 'node' => $node]));

                return $hit;
            }
        }

        $retry = $graph->retryPolicy($node);
        $timeout = $graph->timeoutPolicy($node);
        $maxAttempts = max(1, $retry?->maxAttempts ?? 1);
        $attempt = 0;

        while (true) {
            $attempt++;
            $start = microtime(true);
            try {
                $ctx->setTaskInput($task->input ?? []);
                $output = $graph->node($node)->invoke($state, $ctx);
            } catch (GraphInterrupt $interrupt) {
                throw $interrupt;
            } catch (Throwable $e) {
                if ($retry !== null && $attempt < $maxAttempts && $retry->shouldRetry($e)) {
                    $this->backoff($retry->backoffMs);

                    continue;
                }
                throw $e;
            }

            if ($timeout !== null && $timeout->exceeded(microtime(true) - $start)) {
                $e = new \RuntimeException("Node '{$node}' exceeded timeout of {$timeout->seconds}s.");
                if ($retry !== null && $attempt < $maxAttempts && $retry->shouldRetry($e)) {
                    $this->backoff($retry->backoffMs);

                    continue;
                }
                throw $e;
            }

            if ($cacheKey !== null && is_array($output)) {
                $this->cacheStore->put($cacheKey, $output, $cache->ttlSeconds);
            }

            return $output;
        }
    }

    private function backoff(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }

    /**
     * @param list<PregelTask> $frontier
     */
    private function checkpoint(
        GraphContext $ctx,
        RunState $state,
        array $frontier,
        int $step,
        string $source,
        ?string $parentId,
    ): ?string {
        $saver = $this->saver;
        if ($saver === null) {
            return $parentId;
        }
        $serialized = array_map(static fn (PregelTask $t) => $t->serialize(), $frontier);
        $cp = new Checkpoint(
            id: Checkpoint::newId(),
            parentId: $parentId,
            channelValues: $state->checkpoint(),
            next: $serialized,
            step: $step,
            source: $source,
        );
        $saver->put($ctx->threadId, $cp);
        $ctx->emit($this->event(StreamMode::CHECKPOINTS, ['type' => 'kernel_checkpoint', 'checkpoint_id' => $cp->id, 'step' => $step, 'source' => $source, 'next' => $serialized]));

        return $cp->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function event(string $mode, array $payload): array
    {
        return ['mode' => $mode] + $payload;
    }

    /**
     * @param  list<PregelTask>  $frontier
     * @return list<string>
     */
    private function nodeNames(array $frontier): array
    {
        $names = [];
        foreach ($frontier as $task) {
            $names[$task->node] = true;
        }

        return array_keys($names);
    }

    /** @param list<string> $nodes */
    private function firstInterruptBefore(CompiledGraph $graph, array $nodes): string
    {
        foreach ($nodes as $node) {
            if ($graph->shouldInterruptBefore($node)) {
                return $node;
            }
        }

        return $nodes[0];
    }

    /**
     * Dedup the frontier by task signature (plain tasks collapse by node; Send
     * tasks stay distinct) and drop START/END sentinels.
     *
     * @param  list<PregelTask>  $frontier
     * @return list<PregelTask>
     */
    private function normalize(array $frontier): array
    {
        $seen = [];
        foreach ($frontier as $task) {
            if ($task->node === Constants::END || $task->node === Constants::START) {
                continue;
            }
            $seen[$task->signature()] = $task;
        }

        return array_values($seen);
    }
}
