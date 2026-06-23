<?php

namespace Tests\Unit;

use App\Services\BosskuAi\ReviewerAccessList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewerAccessListTest extends TestCase
{
    private ReviewerAccessList $accessList;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessList = new ReviewerAccessList();
        config([
            'bossku.reviewer_access_list.max_findings' => 12,
            'bossku.reviewer_access_list.max_files' => 25,
        ]);
    }

    #[Test]
    public function it_caps_findings_and_surfaces_critical_ones_first(): void
    {
        $findings = [];
        for ($i = 0; $i < 18; $i++) {
            $findings[] = ['id' => "low-{$i}", 'severity' => 'low', 'title' => "low {$i}"];
        }
        $findings[] = ['id' => 'crit-1', 'severity' => 'critical', 'title' => 'a critical one'];

        $digest = $this->accessList->forFinalReviewer(['findings' => $findings], [], null);

        $this->assertCount(12, $digest['auditor_findings']);
        $this->assertSame('crit-1', $digest['auditor_findings'][0]['id'], 'critical finding is promoted to the top');
        $this->assertSame(7, $digest['auditor_findings_omitted'], '19 findings, 12 kept, 7 omitted');
    }

    #[Test]
    public function it_caps_changed_files_and_reports_how_many_were_omitted(): void
    {
        $files = [];
        for ($i = 0; $i < 30; $i++) {
            $files[] = ['path' => "app/File{$i}.php", 'summary' => 'changed'];
        }

        $digest = $this->accessList->forFinalReviewer([], ['files_changed' => $files], null);

        $this->assertCount(25, $digest['files_changed']);
        $this->assertSame(5, $digest['files_changed_omitted']);
        $this->assertSame('changed', $digest['files_changed'][0]['summary']);
    }

    #[Test]
    public function it_passes_through_security_audit_and_executor_signals(): void
    {
        $digest = $this->accessList->forFinalReviewer(
            ['status' => 'needs_revision', 'summary' => 'issues found'],
            ['patch_summary' => 'edited auth', 'tests_result' => 'pass', 'known_issues' => ['flaky test']],
            ['status' => 'revise', 'critical' => true],
        );

        $this->assertSame('needs_revision', $digest['auditor_status']);
        $this->assertSame(['status' => 'revise', 'critical' => true], $digest['security_audit']);
        $this->assertSame('pass', $digest['tests_result']);
        $this->assertSame(['flaky test'], $digest['known_issues']);
    }
}
