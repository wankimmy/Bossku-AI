<?php

namespace Tests\Unit;

use App\Services\Orchestrator\ExecutorResponseParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorResponseParserTest extends TestCase
{
    #[Test]
    public function it_accepts_summary_without_patch_summary(): void
    {
        $decoded = ['summary' => 'Updated API routes'];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded));
        $normalized = ExecutorResponseParser::normalize($decoded);

        $this->assertSame('success', $normalized['status']);
        $this->assertSame('Updated API routes', $normalized['patch_summary']);
    }

    #[Test]
    public function it_defaults_status_when_only_patch_summary_present(): void
    {
        $decoded = ['patch_summary' => 'Done'];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded));
        $normalized = ExecutorResponseParser::normalize($decoded);

        $this->assertSame('success', $normalized['status']);
        $this->assertSame('Done', $normalized['patch_summary']);
    }

    #[Test]
    public function relaxed_mode_still_accepts_bare_success_for_backward_compatibility(): void
    {
        $this->assertTrue(ExecutorResponseParser::validateForFallback(['status' => 'success']));
    }

    #[Test]
    public function strict_mode_rejects_success_claiming_changes_without_content(): void
    {
        $decoded = [
            'status' => 'success',
            'files_changed' => [['path' => 'app/Foo.php', 'change_type' => 'modified', 'summary' => 'fixed']],
        ];

        $this->assertFalse(ExecutorResponseParser::validateForFallback($decoded, strict: true));
    }

    #[Test]
    public function strict_mode_accepts_success_with_diff_content(): void
    {
        $decoded = [
            'status' => 'success',
            'files_changed' => [[
                'path' => 'app/Foo.php',
                'change_type' => 'modified',
                'diff' => "--- app/Foo.php\n+++ app/Foo.php\n@@ -1 +1 @@\n-old\n+new",
            ]],
        ];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded, strict: true, expectsChanges: true));
    }

    #[Test]
    public function strict_mode_accepts_delete_without_diff_or_after(): void
    {
        $decoded = [
            'status' => 'success',
            'files_changed' => [['path' => 'app/Old.php', 'change_type' => 'deleted']],
        ];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded, strict: true, expectsChanges: true));
    }

    #[Test]
    public function strict_mode_rejects_hallucinated_success_on_write_intent_task(): void
    {
        $decoded = ['status' => 'success', 'patch_summary' => 'All done!'];

        $this->assertFalse(ExecutorResponseParser::validateForFallback($decoded, strict: true, expectsChanges: true));
    }

    #[Test]
    public function strict_mode_accepts_empty_success_when_no_changes_expected(): void
    {
        $decoded = ['status' => 'success', 'patch_summary' => 'Analysis complete'];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded, strict: true, expectsChanges: false));
    }

    #[Test]
    public function strict_mode_accepts_partial_and_blocked_results_without_changes(): void
    {
        $this->assertTrue(ExecutorResponseParser::validateForFallback(
            ['status' => 'partial', 'blockers' => ['cannot proceed']],
            strict: true,
            expectsChanges: true,
        ));
        $this->assertTrue(ExecutorResponseParser::validateForFallback(
            ['status' => 'success', 'needs_user_input' => true],
            strict: true,
            expectsChanges: true,
        ));
    }

    #[Test]
    public function strict_mode_accepts_success_with_only_commands_run(): void
    {
        $decoded = [
            'status' => 'success',
            'commands_run' => [['command' => 'php artisan test']],
        ];

        $this->assertTrue(ExecutorResponseParser::validateForFallback($decoded, strict: true, expectsChanges: true));
    }

    #[Test]
    public function strict_mode_rejects_placeholder_elided_content(): void
    {
        foreach ([
            "function a() {}\n// ...\nfunction b() {}",
            "# ...\ndef real(): pass",
            "class Foo {\n/* ... */\n}",
            "line one\n// ... rest of the file unchanged",
            'existing code omitted',
            'unchanged lines skipped',
            'the remainder is omitted for brevity',
        ] as $content) {
            $decoded = [
                'status' => 'success',
                'files_changed' => [['path' => 'app/Foo.php', 'change_type' => 'modified', 'after' => $content]],
            ];

            $this->assertFalse(
                ExecutorResponseParser::validateForFallback($decoded, strict: true),
                'Expected placeholder rejection for: '.$content,
            );
        }
    }

    #[Test]
    public function strict_mode_does_not_flag_legitimate_ellipsis_code(): void
    {
        foreach ([
            "function sum(...\$args) {\n    return array_sum(\$args);\n}",
            "def stub() -> None: ...\nclass P(Protocol): ...",
            'const rest = {...spread, key: 1};',
        ] as $content) {
            $decoded = [
                'status' => 'success',
                'files_changed' => [['path' => 'app/Foo.php', 'change_type' => 'modified', 'after' => $content]],
            ];

            $this->assertTrue(
                ExecutorResponseParser::validateForFallback($decoded, strict: true),
                'False placeholder positive for: '.$content,
            );
        }
    }
}
