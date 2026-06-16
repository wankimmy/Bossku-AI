<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Orchestrator\SecurityAuditorService;

/**
 * Wraps the real SecurityAuditorService. Writes the security verdict to the
 * `security` channel.
 *
 * @see SecurityAuditorService::audit()
 */
final class SecurityNode implements NodeInterface
{
    public function __construct(private readonly SecurityAuditorService $securityAuditor) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $c = PipelineContext::fromArray($state->get('context', []));
        $ctx->emit(['mode' => 'tasks', 'type' => 'security', 'event' => 'start']);

        $security = $this->securityAuditor->audit(
            $c->prompt,
            $c->modelRoute,
            (array) $state->get('plan', []),
            (array) $state->get('execution', []),
            $c->runId,
            $c->preflightReads,
        );

        return ['security' => $security];
    }
}
