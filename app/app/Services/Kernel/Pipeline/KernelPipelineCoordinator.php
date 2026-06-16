<?php

namespace App\Services\Kernel\Pipeline;

use App\Models\BosskuAi\Run;
use App\Services\Kernel\Checkpoint\DatabaseCheckpointSaver;
use App\Services\Kernel\Graph\DefaultPipelineGraph;
use App\Services\Kernel\KernelMode;
use App\Services\Kernel\Nodes\Pipeline\AuditorNode;
use App\Services\Kernel\Nodes\Pipeline\ExecutorNode;
use App\Services\Kernel\Nodes\Pipeline\FinalReviewerNode;
use App\Services\Kernel\Nodes\Pipeline\MemoryNode;
use App\Services\Kernel\Nodes\Pipeline\PlannerNode;
use App\Services\Kernel\Nodes\Pipeline\RouterNode;
use App\Services\Kernel\Nodes\Pipeline\SecurityNode;
use App\Services\Kernel\Runtime\GraphContext;
use App\Services\Kernel\Runtime\GraphResult;
use App\Services\Kernel\Runtime\GraphRunner;
use App\Services\Orchestrator\AuditorService;
use App\Services\Orchestrator\ExecutorService;
use App\Services\Orchestrator\FinalReviewerService;
use App\Services\Orchestrator\PlannerService;
use App\Services\Orchestrator\SecurityAuditorService;

/**
 * Runs BosskuAI's pipeline through the graph kernel: the default pipeline graph
 * wired to the real PlannerService/ExecutorService/AuditorService/... adapters,
 * with durable per-superstep checkpoints (thread id = run id). This is the
 * BOSSKU_KERNEL=graph execution path. The legacy OrchestratorService remains the
 * default until the eval suite is green on both engines.
 */
final class KernelPipelineCoordinator
{
    public function __construct(
        private readonly PlannerService $planner,
        private readonly ExecutorService $executor,
        private readonly AuditorService $auditor,
        private readonly SecurityAuditorService $securityAuditor,
        private readonly FinalReviewerService $finalReviewer,
    ) {}

    /**
     * Execute a run through the kernel. Returns the same envelope shape as the
     * legacy OrchestratorService::run().
     *
     * @return array<string, mixed>
     */
    public function run(Run $run, PipelineContext $context, ?callable $emit = null): array
    {
        $context = $context->withRunId((string) $run->getKey());
        $graph = $this->buildGraph();

        $input = [
            'context' => $context->toArray(),
            'route' => ['workflow' => $context->workflow],
            'prompt' => $context->prompt,
        ];

        $ctx = new GraphContext((string) $run->getKey(), emit: $emit);
        $result = (new GraphRunner(new DatabaseCheckpointSaver))
            ->run($graph, $input, $ctx, KernelMode::maxSteps());

        return $this->finish($run, $context, $result);
    }

    /**
     * Resume an interrupted/crashed kernel run from its latest checkpoint.
     *
     * @param  array<string, mixed>  $resumeWrites
     * @return array<string, mixed>
     */
    public function resume(Run $run, array $resumeWrites = [], ?callable $emit = null): array
    {
        $graph = $this->buildGraph();
        $ctx = new GraphContext((string) $run->getKey(), emit: $emit);
        $result = (new GraphRunner(new DatabaseCheckpointSaver))
            ->resume($graph, $ctx, $resumeWrites, KernelMode::maxSteps());

        $context = PipelineContext::fromArray((array) ($result->values['context'] ?? []));

        return $this->finish($run, $context, $result);
    }

    private function buildGraph(): \App\Services\Kernel\Graph\CompiledGraph
    {
        return DefaultPipelineGraph::build([
            DefaultPipelineGraph::ROUTER => new RouterNode,
            DefaultPipelineGraph::MEMORY => new MemoryNode,
            DefaultPipelineGraph::PLANNER => new PlannerNode($this->planner),
            DefaultPipelineGraph::EXECUTOR => new ExecutorNode($this->executor),
            DefaultPipelineGraph::AUDITOR => new AuditorNode($this->auditor),
            DefaultPipelineGraph::SECURITY => new SecurityNode($this->securityAuditor),
            DefaultPipelineGraph::FINAL => new FinalReviewerNode($this->finalReviewer),
        ], new DatabaseCheckpointSaver);
    }

    /**
     * @return array<string, mixed>
     */
    private function finish(Run $run, PipelineContext $context, GraphResult $result): array
    {
        $values = $result->values;
        $execution = (array) ($values['execution'] ?? []);
        $final = $values['final'] ?? null;
        $output = (string) ($values['output'] ?? ($execution['output'] ?? ''));

        if ($result->isInterrupted()) {
            $run->update(['status' => 'interrupted']);
        } elseif ($result->isCompleted()) {
            $run->update(['status' => 'completed', 'final_output' => $output]);
        } else {
            $run->update(['status' => 'failed']);
        }

        return [
            'run_id' => (string) $run->getKey(),
            'final_output' => $output,
            'status' => $run->status,
            'kernel' => true,
            'interrupt' => $result->interrupt?->toArray(),
            'metadata' => array_merge((array) $run->metadata, [
                'engine' => 'graph',
                'workflow' => $context->workflow,
                'plan' => $values['plan'] ?? [],
                'executor_result' => $execution,
                'audit_result' => $values['audit'] ?? [],
                'security_audit' => $values['security'] ?? null,
                'final_reviewer' => $final,
                'checkpoint_id' => $result->checkpointId,
                'supersteps' => $result->steps,
            ]),
        ];
    }
}
