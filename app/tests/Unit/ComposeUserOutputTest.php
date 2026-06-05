<?php

namespace Tests\Unit;

use App\Services\Orchestrator\OrchestratorService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ComposeUserOutputTest extends TestCase
{
    #[Test]
    public function it_does_not_imply_restore_when_commands_were_only_proposed(): void
    {
        $summary = $this->invokeSummarize([
            'patch_summary' => 'Restored 5 files from git.',
            'commands_run' => [
                ['command' => 'git restore app/Foo.php'],
            ],
            '_commands_executed' => [],
        ]);

        $this->assertStringContainsString('not executed', strtolower($summary['summary_text']));
        $this->assertSame([], $summary['executed_lines']);
        $this->assertNotEmpty($summary['proposed_lines']);
    }

    #[Test]
    public function it_flags_git_restore_failure_in_summary(): void
    {
        $summary = $this->invokeSummarize([
            'patch_summary' => 'Restored files.',
            'commands_run' => [['command' => 'git restore app/Foo.php']],
            '_commands_executed' => [[
                'command' => 'git restore app/Foo.php',
                'ok' => false,
                'exit_code' => 1,
                'stderr' => 'pathspec did not match',
            ]],
        ]);

        $this->assertTrue($summary['git_restore_failed']);
        $this->assertStringContainsString('did not complete', strtolower($summary['summary_text']));
    }

    #[Test]
    public function compose_user_output_lists_executed_and_proposed_sections(): void
    {
        $output = $this->invokeCompose([
            'status' => 'success',
            'patch_summary' => 'Updated controller.',
            'files_changed' => [],
            'commands_run' => [
                ['command' => 'git restore app/Foo.php'],
                ['command' => 'git status'],
            ],
            '_commands_executed' => [
                ['command' => 'git restore app/Foo.php', 'ok' => true, 'exit_code' => 0],
                ['command' => 'git status', 'ok' => true, 'exit_code' => 0],
            ],
            'git_status_after' => '',
        ], ['status' => 'pass']);

        $this->assertStringContainsString('## Commands executed', $output);
        $this->assertStringContainsString('git restore app/Foo.php (exit 0)', $output);
        $this->assertStringContainsString('## Commands proposed (not run)', $output);
        $this->assertStringNotContainsString('## Checks run', $output);
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function invokeSummarize(array $execResult): array
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'summarizeCommandExecution');
        $method->setAccessible(true);

        return $method->invoke($service, $execResult);
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $audit
     */
    protected function invokeCompose(array $execResult, array $audit): string
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'composeUserOutput');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            $audit,
            $execResult,
            null,
            null,
            ['workflow' => 'test'],
            ['executor' => 'test-model'],
            [],
        );
    }
}
