<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Orchestrator\ExecutorService;

/**
 * Wraps the real ExecutorService. Walks the plan steps, invoking execute() per
 * step with the assembled context, and writes the aggregated result to the
 * `execution` channel.
 *
 * Note: the legacy orchestrator additionally runs revision rounds, evidence
 * reconciliation, command application, and re-indexing around this loop. Those
 * advanced behaviors are not yet ported into the kernel path (tracked as the
 * eval-gated finalization); this node covers the straight-through execution.
 *
 * @see ExecutorService::execute()
 */
final class ExecutorNode implements NodeInterface
{
    public function __construct(private readonly ExecutorService $executor) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $c = PipelineContext::fromArray($state->get('context', []));
        $plan = (array) $state->get('plan', []);
        $steps = is_array($plan['steps'] ?? null) ? $plan['steps'] : [];

        $ctx->emit(['mode' => 'tasks', 'type' => 'executor', 'event' => 'start', 'steps' => count($steps)]);

        $outputs = [];
        foreach ($steps as $step) {
            $outputs[] = $this->executor->execute(
                is_array($step) ? $step : [],
                $c->skillRow,
                $c->ruleLines,
                $c->playbookExcerpt,
                $c->checklistExcerpt,
                $c->allowedTool,
                $plan,
                $c->modelRoute,
                $c->executorProfileKey,
                $c->workspaceContext,
                $c->preflightReads,
                null,
                $c->memoryContext,
                $c->conversation,
                $c->runId,
                $c->specialistContext,
            );
        }

        return [
            'execution' => $outputs[0] ?? [],
            'executor_outputs' => $outputs,
        ];
    }
}
