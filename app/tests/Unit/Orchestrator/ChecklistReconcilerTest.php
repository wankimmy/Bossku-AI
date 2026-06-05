<?php

namespace Tests\Unit\Orchestrator;

use App\Services\Orchestrator\ChecklistReconciler;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChecklistReconcilerTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function samplePlanChecklist(): array
    {
        return [
            [
                'id' => 'plan-1',
                'title' => 'Implement feature',
                'owner' => 'executor',
                'status' => 'pending',
            ],
            [
                'id' => 'plan-2',
                'title' => 'Review',
                'owner' => 'auditor',
                'status' => 'pending',
            ],
        ];
    }

    #[Test]
    public function verified_with_evidence_becomes_completed(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'completed', 'notes' => 'done'],
            ],
            [
                [
                    'id' => 'plan-1',
                    'auditor_verdict' => 'verified',
                    'executor_status' => 'completed',
                ],
            ],
            ['has_evidence' => true, 'proof_files' => ['app/Foo.php']],
        );

        $this->assertSame('completed', $result[0]['status']);
    }

    #[Test]
    public function disputed_stays_disputed(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'completed', 'notes' => 'done'],
            ],
            [
                [
                    'id' => 'plan-1',
                    'auditor_verdict' => 'disputed',
                    'executor_status' => 'completed',
                ],
            ],
            ['has_evidence' => true, 'proof_files' => ['app/Foo.php']],
        );

        $this->assertSame('disputed', $result[0]['status']);
    }

    #[Test]
    public function missing_verdict_entry_is_unverifiable_when_audit_ran(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'completed', 'notes' => 'done'],
            ],
            [
                [
                    'id' => 'plan-1',
                    'auditor_verdict' => 'verified',
                    'executor_status' => 'completed',
                ],
            ],
            ['has_evidence' => true, 'proof_files' => ['app/Foo.php']],
        );

        $this->assertSame('completed', $result[0]['status']);
        $this->assertSame('unverifiable', $result[1]['status']);
    }

    #[Test]
    public function executor_completed_without_evidence_is_unverifiable_without_audit(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'completed', 'notes' => 'done'],
            ],
            [],
            ['has_evidence' => false, 'proof_files' => []],
        );

        $this->assertSame('unverifiable', $result[0]['status']);
    }

    #[Test]
    public function executor_partial_maps_to_needs_revision_without_audit(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'partial', 'notes' => 'half'],
            ],
            [],
            ['has_evidence' => true, 'proof_files' => ['app/Foo.php']],
        );

        $this->assertSame('needs_revision', $result[0]['status']);
    }

    #[Test]
    public function verified_without_evidence_is_unverifiable(): void
    {
        $result = ChecklistReconciler::reconcile(
            $this->samplePlanChecklist(),
            [
                ['id' => 'plan-1', 'status' => 'completed', 'notes' => 'done'],
            ],
            [
                [
                    'id' => 'plan-1',
                    'auditor_verdict' => 'verified',
                    'executor_status' => 'completed',
                ],
            ],
            ['has_evidence' => false, 'proof_files' => []],
        );

        $this->assertSame('unverifiable', $result[0]['status']);
    }

    #[Test]
    public function summarize_checklist_counts_verified_and_issues(): void
    {
        $stats = ChecklistReconciler::summarizeChecklist([
            ['id' => 'a', 'status' => 'completed'],
            ['id' => 'b', 'status' => 'disputed'],
            ['id' => 'c', 'status' => 'unverifiable'],
        ]);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['verified']);
        $this->assertSame(1, $stats['disputed']);
        $this->assertSame(1, $stats['unverifiable']);
        $this->assertTrue($stats['has_issues']);
    }

    #[Test]
    public function evidence_from_executor_result_detects_proof_files_and_commands(): void
    {
        $withFiles = ChecklistReconciler::evidenceFromExecutorResult([
            'files_changed' => [['path' => 'pages/index.vue']],
        ]);
        $this->assertTrue($withFiles['has_evidence']);

        $withCommand = ChecklistReconciler::evidenceFromExecutorResult([
            '_commands_executed' => [['command' => 'php artisan test', 'ok' => true]],
        ]);
        $this->assertTrue($withCommand['has_evidence']);

        $empty = ChecklistReconciler::evidenceFromExecutorResult([]);
        $this->assertFalse($empty['has_evidence']);
    }
}
