<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;

/**
 * Surfaces the retrieved memory context (assembled upstream by the coordinator)
 * onto the blackboard for downstream nodes and the timeline.
 */
final class MemoryNode implements NodeInterface
{
    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $context = PipelineContext::fromArray($state->get('context', []));
        $ctx->emit(['mode' => 'updates', 'type' => 'memory', 'count' => count($context->memoryContext)]);

        return ['memory' => $context->memoryContext];
    }
}
