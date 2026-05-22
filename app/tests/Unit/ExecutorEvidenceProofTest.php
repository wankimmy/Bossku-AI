<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorEvidenceProofTest extends TestCase
{
    #[Test]
    public function executor_payload_for_audit_includes_tool_evidence_and_proof_files(): void
    {
        $payload = ExecutorEvidenceSupport::executorPayloadForAudit([
            'status' => 'success',
            'patch_summary' => 'done',
            'files_read' => [['path' => 'app/Models/User.php']],
            'files_changed' => [],
        ], [
            ['path' => 'app/Models/User.php', 'preview' => 'class User', 'found' => true],
        ]);

        $this->assertArrayHasKey('tool_evidence', $payload);
        $this->assertArrayHasKey('proof_files', $payload);
        $this->assertContains('app/Models/User.php', $payload['proof_files']);
    }

    #[Test]
    public function auditor_payload_for_revision_merges_audit_and_executor(): void
    {
        $payload = ExecutorEvidenceSupport::auditorPayloadForRevision(
            ['status' => 'needs_revision', 'findings' => [], 'required_fixes' => ['fix auth']],
            ['status' => 'partial', 'files_changed' => [['path' => 'app/Http/Kernel.php']]],
            [],
        );

        $this->assertSame('needs_revision', $payload['audit']['status']);
        $this->assertArrayHasKey('executor_result', $payload);
        $this->assertContains('app/Http/Kernel.php', $payload['proof_files']);
    }
}
