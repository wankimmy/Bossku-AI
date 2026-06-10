<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorEvidenceSupportTest extends TestCase
{
    #[Test]
    public function count_files_read_only_includes_found_true(): void
    {
        $execResult = [
            'files_read' => [
                ['path' => 'routes/web.php', 'found' => true],
                ['path' => 'FiuuPaymentController.php', 'found' => false],
                ['path' => '', 'found' => true],
            ],
        ];

        $this->assertSame(1, ExecutorEvidenceSupport::countFilesRead($execResult));
        $this->assertSame(1, ExecutorEvidenceSupport::countFilesReadFailed($execResult));
    }

    #[Test]
    public function merge_apply_report_downgrades_success_on_write_errors(): void
    {
        $execResult = ['status' => 'success', 'known_issues' => []];
        $report = [
            'applied' => ['app/Ok.php'],
            'skipped' => ['app/NoContent.php (no after/new_contents/diff)'],
            'errors' => ['app/Fail.php: permission denied'],
        ];

        $merged = ExecutorEvidenceSupport::mergeApplyReport($execResult, $report);

        $this->assertSame('partial', $merged['status']);
        $this->assertContains('File write failed: app/Fail.php: permission denied', $merged['known_issues']);
        $this->assertContains('File write skipped: app/NoContent.php (no after/new_contents/diff)', $merged['known_issues']);
    }

    #[Test]
    public function merge_apply_report_is_a_noop_when_everything_applied(): void
    {
        $execResult = ['status' => 'success', 'known_issues' => []];
        $report = ['applied' => ['app/Ok.php'], 'skipped' => [], 'errors' => []];

        $this->assertSame($execResult, ExecutorEvidenceSupport::mergeApplyReport($execResult, $report));
    }

    #[Test]
    public function merge_apply_report_keeps_failed_status_and_existing_issues(): void
    {
        $execResult = ['status' => 'failed', 'known_issues' => ['earlier issue']];
        $report = ['applied' => [], 'skipped' => [], 'errors' => ['app/Fail.php: disk full']];

        $merged = ExecutorEvidenceSupport::mergeApplyReport($execResult, $report);

        $this->assertSame('failed', $merged['status']);
        $this->assertContains('earlier issue', $merged['known_issues']);
        $this->assertContains('File write failed: app/Fail.php: disk full', $merged['known_issues']);
    }
}
