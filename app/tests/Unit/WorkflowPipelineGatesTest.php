<?php

namespace Tests\Unit;

use App\Services\BosskuAi\WorkflowRouteHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowPipelineGatesTest extends TestCase
{
    #[DataProvider('auditorWorkflowProvider')]
    #[Test]
    public function workflow_includes_auditor_only_when_named(string $workflow, bool $expectsAuditor): void
    {
        $this->assertSame($expectsAuditor, WorkflowRouteHelper::workflowIncludesAuditor($workflow));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function auditorWorkflowProvider(): array
    {
        return [
            'plan and execute' => ['orchestrator_executor', false],
            'with auditor' => ['orchestrator_executor_auditor', true],
            'security chain' => ['orchestrator_executor_auditor_security', true],
            'full chain' => ['orchestrator_executor_auditor_security_final_reviewer', true],
            'orchestrator only' => ['orchestrator_only', false],
            'direct answer' => ['direct_answer', false],
        ];
    }

    #[Test]
    public function skipped_agents_for_short_route_lists_review_stages(): void
    {
        $skipped = WorkflowRouteHelper::skippedAgentsForRoute([
            'workflow' => 'orchestrator_executor',
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'needs_executor' => true,
        ]);

        $this->assertContains('auditor', $skipped);
        $this->assertContains('security-auditor', $skipped);
        $this->assertContains('final-reviewer', $skipped);
        $this->assertNotContains('executor', $skipped);
    }

    #[Test]
    public function pipeline_agents_for_executor_route(): void
    {
        $agents = WorkflowRouteHelper::pipelineAgentsForWorkflow('orchestrator_executor');
        $this->assertSame(['orchestrator', 'executor'], $agents);
    }
}
