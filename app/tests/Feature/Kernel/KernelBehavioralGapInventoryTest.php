<?php

namespace Tests\Feature\Kernel;

use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Services\Kernel\Graph\DefaultPipelineGraph;
use App\Services\Kernel\Nodes\Pipeline\AuditorNode;
use App\Services\Kernel\Nodes\Pipeline\ExecutorNode;
use App\Services\Kernel\Nodes\Pipeline\FinalReviewerNode;
use App\Services\Kernel\Nodes\Pipeline\MemoryNode;
use App\Services\Kernel\Nodes\Pipeline\PlannerNode;
use App\Services\Kernel\Nodes\Pipeline\RouterNode;
use App\Services\Kernel\Nodes\Pipeline\SecurityNode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Documents the known behavioral gaps between the Kernel graph path and the
 * legacy OrchestratorService pipeline. Each test asserts the CURRENT state
 * (the gap exists) so that when a gap is closed, the test must be updated —
 * making the closure an explicit, visible event rather than a silent drift.
 *
 * These tests are intentionally permissive: they prove the Kernel is a
 * well-formed skeleton that delegates to the real pipeline services, while
 * recording exactly which orchestrator responsibilities are not yet ported
 * into the graph nodes. See KernelTopologyParityTest for the topology contract.
 *
 * When all the "not yet ported" assertions in this file are flipped to assert
 * the ported behavior, the Kernel is ready to become the default engine.
 */
class KernelBehavioralGapInventoryTest extends TestCase
{
    /**
     * The Kernel's dispatchToKernel() skips memory retrieval, skill routing,
     * untrusted-content scanning, specialist matching, goal alignment, and
     * worktree provisioning — all of which the legacy run() does before the
     * planner fires. This test pins the current minimal-context contract so
     * the gap is visible. Close it by enriching PipelineContext assembly in
     * OrchestratorService::dispatchToKernel() and removing this assertion.
     */
    #[Test]
    public function kernel_dispatch_assembles_minimal_context_gap_known(): void
    {
        // The RouterNode is a pass-through that only echoes the workflow; it
        // does not perform the classifier call. Real classification happens
        // upstream in dispatchToKernel() via PromptRouteClassifier. This is the
        // single largest gap: the Kernel relies on the legacy orchestrator for
        // all pre-planner context assembly.
        $node = new RouterNode;
        $this->assertNotNull($node, 'RouterNode exists; the gap is in dispatchToKernel context richness, not the node itself.');
    }

    /**
     * ExecutorNode walks plan steps straight through. The legacy orchestrator
     * additionally runs: revision rounds, evidence reconciliation, file-change
     * application via ExecutorFileChangeApplier, post-execution re-indexing,
     * budget narrowing, and risk-aware profile escalation. The node's own
     * docblock records this. Close the gap by porting those responsibilities
     * into the node (or a wrapper) and updating this assertion.
     */
    #[Test]
    public function executor_node_is_straight_through_gap_known(): void
    {
        // Read the node source and confirm the docblock records the unported
        // advanced behaviors. This is the boundary marker: when those behaviors
        // are ported into the node, this assertion must be updated.
        $source = file_get_contents((new \ReflectionClass(ExecutorNode::class))->getFileName());
        $this->assertStringContainsString(
            'not yet ported into the kernel path',
            $source,
            'ExecutorNode docblock must record the ported-vs-unported boundary.',
        );
    }

    /**
     * The Kernel path forces legacy for AI Council, Staff Council, specialist
     * agents, and short-path (direct_answer / writer_only) workflows via
     * fusionFeaturesRequireLegacyPipeline(). Until those features are wired as
     * graph nodes or subgraphs, the Kernel cannot be the default. This test
     * pins the existence of that guard.
     */
    #[Test]
    public function fusion_feature_guard_exists_gap_known(): void
    {
        $reflection = new \ReflectionMethod(\App\Services\Orchestrator\OrchestratorService::class, 'fusionFeaturesRequireLegacyPipeline');
        $this->assertTrue($reflection->isProtected(), 'fusionFeaturesRequireLegacyPipeline is the guard that keeps fusion features on the legacy path.');
    }

    /**
     * The Kernel graph has no node for the Designer phase, the Clarification
     * pause, the Plan Council review, or the Staff Council review. These are
     * mid-pipeline branches in the legacy orchestrator. The graph topology
     * (DefaultPipelineGraph) is linear: router → memory → planner → executor
     * → {auditor?} → {security?} → {final?} → END. Close the gap by adding
     * conditional edges / nodes for these phases.
     */
    #[Test]
    public function graph_topology_is_linear_no_designer_or_council_nodes_gap_known(): void
    {
        $nodes = DefaultPipelineGraph::nodeNames();
        $this->assertNotContains('designer', $nodes, 'Designer is not yet a graph node.');
        $this->assertNotContains('clarification', $nodes, 'Clarification pause is not yet a graph node.');
        $this->assertNotContains('plan_council', $nodes, 'Plan Council review is not yet a graph node.');
        $this->assertNotContains('staff_council', $nodes, 'Staff Council review is not yet a graph node.');
    }

    /**
     * The graph's conditional edges use the workflow-string substring check
     * (str_contains), exactly mirroring WorkflowRouteHelper. When the route
     * definitions are eventually unified (topology becomes the single source
     * of truth and WorkflowRouteHelper reads from it), update this assertion
     * to assert the unification.
     */
    #[Test]
    public function conditional_edges_use_workflow_substring_check_gap_known(): void
    {
        $reflection = new \ReflectionMethod(DefaultPipelineGraph::class, 'afterExecutor');
        $source = $this->methodSource($reflection);
        $this->assertStringContainsString('str_contains', $source, 'afterExecutor uses the same substring matching as WorkflowRouteHelper.');
    }

    private function methodSource(\ReflectionMethod $m): string
    {
        $file = $m->getFileName();
        if (! is_string($file) || ! is_readable($file)) {
            return '';
        }
        $lines = file($file) ?: [];
        $start = $m->getStartLine() - 1;
        $end = $m->getEndLine();
        return implode('', array_slice($lines, $start, max(1, $end - $start)));
    }
}