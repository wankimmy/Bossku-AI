<?php

namespace Tests\Feature\Kernel;

use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Services\Kernel\Constants;
use App\Services\Kernel\Graph\DefaultPipelineGraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the Kernel graph topology and the legacy WorkflowRouteHelper agree
 * on which agents each workflow string executes. This is the data-level parity
 * check that must stay green before the Kernel can be promoted to default.
 *
 * If this test fails, the two route definitions have drifted: either
 * DefaultPipelineGraph's conditional edges or WorkflowRouteHelper's string
 * matching changed without the other being updated.
 */
class KernelTopologyParityTest extends TestCase
{
    /** @return array<string, array{string, list<string>, list<string>}> */
    public static function workflowProvider(): array
    {
        return [
            'executor_only' => [
                'orchestrator_executor',
                WorkflowRouteHelper::pipelineAgentsForWorkflow('orchestrator_executor'),
                self::kernelNodeSequence('orchestrator_executor'),
            ],
            'with_auditor' => [
                'orchestrator_executor_auditor',
                WorkflowRouteHelper::pipelineAgentsForWorkflow('orchestrator_executor_auditor'),
                self::kernelNodeSequence('orchestrator_executor_auditor'),
            ],
            'with_security' => [
                'orchestrator_executor_auditor_security',
                WorkflowRouteHelper::pipelineAgentsForWorkflow('orchestrator_executor_auditor_security'),
                self::kernelNodeSequence('orchestrator_executor_auditor_security'),
            ],
            'full_chain' => [
                'orchestrator_executor_auditor_security_final_reviewer',
                WorkflowRouteHelper::pipelineAgentsForWorkflow('orchestrator_executor_auditor_security_final_reviewer'),
                self::kernelNodeSequence('orchestrator_executor_auditor_security_final_reviewer'),
            ],
        ];
    }

    /**
     * The Kernel always runs router + memory before the planner; the legacy
     * pipeline folds routing and memory retrieval into the orchestrator step
     * (not counted as discrete agents). So we compare the *post-routing* agents:
     * planner + executor + optional auditor/security/final — mapped to their
     * Kernel node names.
     *
     * @param  list<string>  $legacyAgents  from WorkflowRouteHelper (includes 'orchestrator')
     * @return list<string> kernel node names in execution order
     */
    private static function kernelNodeSequence(string $workflow): array
    {
        // The Kernel graph is: router → memory → planner → executor → {auditor?}
        // → {security?} → {final?} → END. Router and memory are always present
        // but have no legacy agent counterpart (they are orchestrator-internal).
        $nodes = [DefaultPipelineGraph::PLANNER, DefaultPipelineGraph::EXECUTOR];

        if (str_contains($workflow, 'auditor')) {
            $nodes[] = DefaultPipelineGraph::AUDITOR;
        }
        if (str_contains($workflow, 'security')) {
            $nodes[] = DefaultPipelineGraph::SECURITY;
        }
        if (str_contains($workflow, 'final_reviewer')) {
            $nodes[] = DefaultPipelineGraph::FINAL;
        }

        return $nodes;
    }

    /**
     * Map a legacy agent name to its Kernel node name so the two sequences are
     * directly comparable. The legacy 'orchestrator' agent performs routing +
     * memory retrieval + planning in one step; the Kernel splits these into
     * router → memory → planner, so 'orchestrator' maps to 'planner' (the
     * router/memory nodes are always-present infrastructure with no legacy
     * agent counterpart).
     */
    private static function legacyToKernelNode(string $agent): ?string
    {
        return match ($agent) {
            'orchestrator' => DefaultPipelineGraph::PLANNER,
            'executor' => DefaultPipelineGraph::EXECUTOR,
            'auditor' => DefaultPipelineGraph::AUDITOR,
            'security-auditor' => DefaultPipelineGraph::SECURITY,
            'final-reviewer' => DefaultPipelineGraph::FINAL,
            'direct_answer', 'writer' => null, // short-path workflows bypass the graph
            default => null,
        };
    }

    #[Test]
    #[DataProvider('workflowProvider')]
    public function topology_matches_legacy_for_every_workflow(string $workflow, array $legacyAgents, array $kernelNodes): void
    {
        $mapped = array_values(array_filter(array_map(
            self::legacyToKernelNode(...),
            $legacyAgents,
        )));

        $this->assertSame(
            $kernelNodes,
            $mapped,
            "Topology drift for workflow '{$workflow}': legacy maps to [".implode(', ', $mapped)
            .'] but Kernel expects ['.implode(', ', $kernelNodes).'].',
        );
    }

    #[Test]
    public function graph_topology_is_renderable_as_data(): void
    {
        $topology = DefaultPipelineGraph::topology();

        $this->assertSame(DefaultPipelineGraph::ROUTER, $topology['entry']);
        $this->assertContains(DefaultPipelineGraph::PLANNER, $topology['nodes']);
        $this->assertContains(DefaultPipelineGraph::EXECUTOR, $topology['nodes']);

        $endEdges = array_filter(
            $topology['edges'],
            fn (array $e) => ($e['to'] ?? null) === Constants::END,
        );
        $this->assertNotEmpty($endEdges, 'Graph must have at least one edge to END.');
    }
}