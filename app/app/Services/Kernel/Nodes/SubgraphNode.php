<?php

namespace App\Services\Kernel\Nodes;

use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\CompiledGraph;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Kernel\Types\GraphInterrupt;

/**
 * Runs a compiled graph as a single node (composition). The child receives a
 * slice of the parent state as input and returns a slice of its result as the
 * node's update.
 *
 * Phase 3 runs the child synchronously and ephemerally (no nested checkpoint
 * persistence); a child interrupt bubbles to the parent as a GraphInterrupt.
 */
final class SubgraphNode implements NodeInterface
{
    /**
     * @param  list<string>|null  $inputKeys   parent channels passed in (null = all)
     * @param  list<string>|null  $outputKeys  child channels written back (null = all)
     */
    public function __construct(
        private readonly CompiledGraph $child,
        private readonly ?array $inputKeys = null,
        private readonly ?array $outputKeys = null,
        private readonly int $maxSteps = 100,
    ) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $values = $state->values();
        $input = $this->inputKeys === null
            ? $values
            : array_intersect_key($values, array_flip($this->inputKeys));

        $childCtx = new GraphContext($ctx->threadId.':sub', step: 0, store: $ctx->store);
        $result = (new GraphRunner)->run($this->child, $input, $childCtx, $this->maxSteps);

        if ($result->isInterrupted()) {
            // Bubble up; the parent run suspends. (Nested resume is a later refinement.)
            throw new GraphInterrupt($result->interrupt?->value);
        }

        if ($result->status !== Constants::STATUS_COMPLETED) {
            return [];
        }

        return $this->outputKeys === null
            ? $result->values
            : array_intersect_key($result->values, array_flip($this->outputKeys));
    }
}
