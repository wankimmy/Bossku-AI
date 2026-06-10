<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorEvidenceSupport;
use App\Services\Orchestrator\ExecutorPatchPreflight;
use App\Services\Project\FileWriteApplier;
use App\Services\Project\ProjectPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorPatchPreflightTest extends TestCase
{
    private string $tmpFile = '';

    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        parent::tearDown();
    }

    private function preflight(?string $fileContent = null): ExecutorPatchPreflight
    {
        $paths = $this->createMock(ProjectPathResolver::class);
        if ($fileContent === null) {
            $paths->method('resolve')->willThrowException(new \RuntimeException('Path denied or not found.'));
        } else {
            $this->tmpFile = (string) tempnam(sys_get_temp_dir(), 'bossku-preflight-');
            file_put_contents($this->tmpFile, $fileContent);
            $paths->method('resolve')->willReturn([
                'absolute' => $this->tmpFile,
                'relative' => 'app/Target.php',
            ]);
        }

        return new ExecutorPatchPreflight(app(FileWriteApplier::class), $paths);
    }

    #[Test]
    public function flags_change_claims_without_content(): void
    {
        $problems = $this->preflight()->problems([
            'files_changed' => [['path' => 'app/Foo.php', 'change_type' => 'modified', 'summary' => 'fixed it']],
        ]);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('neither `diff` nor `after`', $problems[0]);
    }

    #[Test]
    public function flags_placeholder_elided_content(): void
    {
        $problems = $this->preflight()->problems([
            'files_changed' => [[
                'path' => 'app/Foo.php',
                'change_type' => 'modified',
                'after' => "function a() {}\n// ... rest of the file unchanged",
            ]],
        ]);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('placeholder', $problems[0]);
    }

    #[Test]
    public function flags_merge_conflict_markers(): void
    {
        $problems = $this->preflight()->problems([
            'files_changed' => [[
                'path' => 'app/Foo.php',
                'change_type' => 'modified',
                'after' => "a\n<<<<<<< HEAD\nb\n=======\nc\n>>>>>>> branch\n",
            ]],
        ]);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('merge-conflict markers', $problems[0]);
    }

    #[Test]
    public function flags_diff_that_does_not_apply_to_the_current_file(): void
    {
        $problems = $this->preflight("alpha\nbeta\n")->problems([
            'files_changed' => [[
                'path' => 'app/Target.php',
                'change_type' => 'modified',
                'diff' => "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n alpha\n-GONE\n+replacement",
            ]],
        ]);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('does not apply', $problems[0]);
    }

    #[Test]
    public function passes_a_clean_applying_diff(): void
    {
        $problems = $this->preflight("alpha\nbeta\n")->problems([
            'files_changed' => [[
                'path' => 'app/Target.php',
                'change_type' => 'modified',
                'diff' => "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n alpha\n-beta\n+BETA",
            ]],
        ]);

        $this->assertSame([], $problems);
    }

    #[Test]
    public function skips_dry_run_when_path_cannot_be_inspected(): void
    {
        $problems = $this->preflight()->problems([
            'files_changed' => [[
                'path' => 'app/Foo.php',
                'change_type' => 'modified',
                'diff' => "--- a/f\n+++ b/f\n@@ -1 +1 @@\n-x\n+y",
            ]],
        ]);

        $this->assertSame([], $problems);
    }

    #[Test]
    public function skips_dry_run_for_files_already_auto_applied(): void
    {
        $problems = $this->preflight("totally\ndifferent\n")->problems([
            'files_changed' => [[
                'path' => 'app/Target.php',
                'change_type' => 'modified',
                'diff' => "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n alpha\n-GONE\n+replacement",
            ]],
            '_files_applied' => ['applied' => ['app/Target.php'], 'skipped' => [], 'errors' => []],
        ]);

        $this->assertSame([], $problems);
    }

    #[Test]
    public function ignores_deletes_and_passes_complete_after_content(): void
    {
        $problems = $this->preflight()->problems([
            'files_changed' => [
                ['path' => 'app/Old.php', 'change_type' => 'deleted'],
                ['path' => 'app/New.php', 'change_type' => 'created', 'after' => "<?php\n\nfinal class A {}\n"],
            ],
        ]);

        $this->assertSame([], $problems);
    }

    #[Test]
    public function deterministic_verdict_has_the_audit_loop_shape(): void
    {
        $audit = ExecutorEvidenceSupport::deterministicPatchPrecheckFailed(['app/Foo.php: bad diff']);

        $this->assertSame('needs_revision', $audit['status']);
        $this->assertFalse($audit['_legacy_pass']);
        $this->assertTrue($audit['_deterministic']);
        $this->assertSame(['app/Foo.php: bad diff'], $audit['required_fixes']);
        $this->assertSame('open', $audit['findings'][0]['status']);
    }
}
