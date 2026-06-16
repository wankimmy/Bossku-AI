<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Orchestrator\AuditorService;

/**
 * Wraps the real AuditorService. Audits the executor result against the plan and
 * appends the verdict to the `audit` channel (a Topic).
 *
 * @see AuditorService::auditStep()
 */
final class AuditorNode implements NodeInterface
{
    public function __construct(private readonly AuditorService $auditor) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $c = PipelineContext::fromArray($state->get('context', []));
        $plan = (array) $state->get('plan', []);
        $steps = is_array($plan['steps'] ?? null) ? $plan['steps'] : [];
        $firstStep = is_array($steps[0] ?? null) ? $steps[0] : [];

        $ctx->emit(['mode' => 'tasks', 'type' => 'auditor', 'event' => 'start']);

        $audit = $this->auditor->auditStep(
            $c->prompt,
            $c->routerContext,
            $c->modelRoute,
            $plan,
            $firstStep,
            (array) $state->get('execution', []),
            $c->ruleLines,
            $c->checklistExcerpt,
            $c->highRiskContext,
            $c->preflightReads,
            $c->runId,
            $c->memoryContext,
            $c->conversation,
        );

        return ['audit' => $audit];
    }
}
