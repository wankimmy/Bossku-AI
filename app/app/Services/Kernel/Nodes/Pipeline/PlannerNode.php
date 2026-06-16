<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Orchestrator\PlannerService;

/**
 * Wraps the real PlannerService. Calls plan() with the assembled context and
 * writes the ExecutionPlan to the `plan` channel.
 *
 * @see PlannerService::plan()
 */
final class PlannerNode implements NodeInterface
{
    public function __construct(private readonly PlannerService $planner) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $c = PipelineContext::fromArray($state->get('context', []));
        $ctx->emit(['mode' => 'tasks', 'type' => 'planner', 'event' => 'start']);

        $plan = $this->planner->plan(
            $c->prompt,
            $c->memoryContext,
            $c->routerContext,
            $c->modelRoute,
            $c->conversation,
            $c->runId,
        );

        return ['plan' => $plan];
    }
}
