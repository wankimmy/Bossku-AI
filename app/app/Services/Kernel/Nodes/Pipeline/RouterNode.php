<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;

/**
 * Entry node. Routing/classification is assembled upstream by the coordinator
 * and seeded into the `route`/`context` channels, so this node surfaces the
 * chosen workflow and passes control to memory. Kept as a real node so the
 * topology (and Studio view) matches the legacy pipeline shape.
 */
final class RouterNode implements NodeInterface
{
    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $context = PipelineContext::fromArray($state->get('context', []));
        $ctx->emit(['mode' => 'updates', 'type' => 'router', 'workflow' => $context->workflow]);

        return ['route' => ['workflow' => $context->workflow]];
    }
}
