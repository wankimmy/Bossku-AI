<?php

namespace App\Services\Kernel\Nodes\Pipeline;

use App\Services\Kernel\Nodes\NodeInterface;
use App\Services\Kernel\Pipeline\PipelineContext;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\RunState;
use App\Services\Kernel\Types\Command;
use App\Services\Orchestrator\FinalReviewerService;

/**
 * Wraps the real FinalReviewerService. Produces the completion summary and
 * writes it to the `final` and `output` channels.
 *
 * @see FinalReviewerService::review()
 */
final class FinalReviewerNode implements NodeInterface
{
    public function __construct(private readonly FinalReviewerService $finalReviewer) {}

    public function invoke(RunState $state, GraphContext $ctx): array|Command
    {
        $c = PipelineContext::fromArray($state->get('context', []));
        $audit = (array) $state->get('audit', []);
        $lastAudit = is_array(end($audit) ?: null) ? end($audit) : [];

        $ctx->emit(['mode' => 'tasks', 'type' => 'final', 'event' => 'start']);

        $final = $this->finalReviewer->review(
            $c->prompt,
            $c->modelRoute,
            $lastAudit,
            $state->get('security'),
            (array) $state->get('execution', []),
            (array) $state->get('plan', []),
            $c->memoryContext,
            $c->conversation,
            $c->runId,
        );

        return [
            'final' => $final,
            'output' => (string) ($final['summary'] ?? $final['output'] ?? ''),
        ];
    }
}
