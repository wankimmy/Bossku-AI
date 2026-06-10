<?php

namespace Tests\Unit;

use App\Services\Project\FileWriteApplier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FileWriteApplierDiffTest extends TestCase
{
    private function applier(): FileWriteApplier
    {
        return app(FileWriteApplier::class);
    }

    #[Test]
    public function applies_a_standard_hunk_with_header(): void
    {
        $before = "line1\nline2\nline3\n";
        $diff = "--- a/f.txt\n+++ b/f.txt\n@@ -1,3 +1,3 @@\n line1\n-line2\n+LINE2\n line3";

        $this->assertSame("line1\nLINE2\nline3\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function applies_a_mid_file_hunk_without_dropping_leading_lines(): void
    {
        $lines = [];
        for ($i = 1; $i <= 10; $i++) {
            $lines[] = 'line'.$i;
        }
        $before = implode("\n", $lines)."\n";
        $diff = "--- a/f\n+++ b/f\n@@ -5,3 +5,3 @@\n line5\n-line6\n+CHANGED\n line7";

        $expected = str_replace('line6', 'CHANGED', $before);
        $this->assertSame($expected, $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function applies_multiple_hunks_preserving_between_content(): void
    {
        $before = "a\nb\nc\nd\ne\nf\ng\nh\n";
        $diff = "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n a\n-b\n+B\n@@ -6,2 +6,2 @@\n f\n-g\n+G";

        $this->assertSame("a\nB\nc\nd\ne\nf\nG\nh\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function recovers_when_hunk_header_line_numbers_are_stale(): void
    {
        $before = "a\nb\nc\nd\ne\nf\n";
        // Context says d→D but the header points at the wrong line.
        $diff = "--- a/f\n+++ b/f\n@@ -1,3 +1,3 @@\n c\n-d\n+D\n e";

        $this->assertSame("a\nb\nc\nD\ne\nf\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function returns_null_when_context_does_not_match_the_file(): void
    {
        $before = "alpha\nbeta\n";
        $diff = "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n alpha\n-GONE\n+replacement";

        $this->assertNull($this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function returns_null_for_ambiguous_context_matches(): void
    {
        $before = "x\ny\nx\ny\n";
        $diff = "--- a/f\n+++ b/f\n@@ -9,2 +9,2 @@\n x\n-y\n+Y";

        $this->assertNull($this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function applies_pure_insertion_hunk_after_the_declared_line(): void
    {
        $before = "one\ntwo\nthree\n";
        $diff = "--- a/f\n+++ b/f\n@@ -2,0 +3,1 @@\n+inserted";

        $this->assertSame("one\ntwo\ninserted\nthree\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function applies_creation_diff_against_empty_before(): void
    {
        $diff = "--- /dev/null\n+++ b/new.txt\n@@ -0,0 +1,2 @@\n+first\n+second";

        $this->assertSame("first\nsecond\n", $this->applier()->applyUnifiedDiff('', $diff));
    }

    #[Test]
    public function tolerates_trailing_whitespace_differences_in_context(): void
    {
        $before = "line1   \nline2\n";
        $diff = "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n line1\n-line2\n+LINE2";

        $this->assertSame("line1   \nLINE2\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function ignores_no_newline_markers_and_git_headers(): void
    {
        $before = "a\nb\n";
        $diff = "diff --git a/f b/f\nindex 123..456 100644\n--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n a\n-b\n+B\n\\ No newline at end of file";

        $this->assertSame("a\nB\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function preserves_absence_of_trailing_newline(): void
    {
        $before = "a\nb";
        $diff = "--- a/f\n+++ b/f\n@@ -1,2 +1,2 @@\n a\n-b\n+B";

        $this->assertSame("a\nB", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function applies_headerless_diff_by_content(): void
    {
        $before = "one\ntwo\nthree\n";
        $diff = "--- f\n+++ f\n one\n-two\n+TWO\n three";

        $this->assertSame("one\nTWO\nthree\n", $this->applier()->applyUnifiedDiff($before, $diff));
    }

    #[Test]
    public function returns_null_for_prose_or_garbage(): void
    {
        $this->assertNull($this->applier()->applyUnifiedDiff("a\n", 'I changed the file as requested.'));
        $this->assertNull($this->applier()->applyUnifiedDiff("a\n", ''));
    }
}
