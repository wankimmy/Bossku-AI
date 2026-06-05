<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorEvidenceTest extends TestCase
{
    #[Test]
    public function merge_preflight_reads_includes_missing_files(): void
    {
        $exec = ['status' => 'success', 'files_read' => []];
        $preflight = [
            ['path' => 'composer.json', 'found' => false, 'reason' => 'bootstrap'],
            ['path' => 'README.md', 'found' => true, 'reason' => 'bootstrap'],
        ];

        $merged = ExecutorEvidenceSupport::mergePreflightReads($exec, $preflight);

        $this->assertCount(2, $merged['files_read']);
        $this->assertSame('composer.json', $merged['files_read'][0]['path']);
        $this->assertSame('README.md', $merged['files_read'][1]['path']);
    }

    #[Test]
    public function executor_payload_for_audit_includes_files_read(): void
    {
        $payload = ExecutorEvidenceSupport::executorPayloadForAudit([
            'status' => 'success',
            'patch_summary' => 'done',
            'files_read' => [['path' => 'src/foo.php', 'reason' => 'audit']],
            'files_changed' => [],
            'commands_run' => [],
        ]);

        $this->assertArrayHasKey('files_read', $payload);
        $this->assertCount(1, $payload['files_read']);
    }

    #[Test]
    public function has_read_evidence_when_files_read_present(): void
    {
        $this->assertTrue(ExecutorEvidenceSupport::hasReadEvidence([
            'files_read' => [['path' => 'a.txt']],
        ], []));
        $this->assertFalse(ExecutorEvidenceSupport::hasReadEvidence(['files_read' => []], []));
        $this->assertTrue(ExecutorEvidenceSupport::hasReadEvidence(
            ['files_read' => []],
            [['tool' => 'file_read_safe', 'path' => 'b.txt', 'found' => true]],
        ));
    }

    #[Test]
    public function deterministic_no_files_read_has_revise_status(): void
    {
        $result = ExecutorEvidenceSupport::deterministicNoFilesRead();

        $this->assertSame('revise', $result['status']);
        $this->assertTrue($result['_deterministic']);
        $this->assertSame([], $result['security_issues']);
    }
}
