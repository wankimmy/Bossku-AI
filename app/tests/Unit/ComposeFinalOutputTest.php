<?php

namespace Tests\Unit;

use App\Services\Orchestrator\OrchestratorService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ComposeFinalOutputTest extends TestCase
{
    /**
     * @param  list<string>  $files
     * @param  array<string, mixed>  $commandOutcome
     * @param  list<string>  $executedLines
     * @param  list<string>  $proposedLines
     * @param  array<string, mixed>|null  $lastFinal
     */
    private function buildNextPrompt(
        array $files,
        array $commandOutcome,
        array $executedLines,
        array $proposedLines,
        string $nextStep,
        ?array $lastFinal,
    ): string {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'buildNextPrompt');
        $method->setAccessible(true);

        return (string) $method->invoke(
            $service,
            $files,
            $commandOutcome,
            $executedLines,
            $proposedLines,
            $nextStep,
            $lastFinal,
        );
    }

    /**
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>|null  $lastFinal
     * @param  array<string, mixed>|null  $lastSecurity
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  list<array<string, mixed>>  $memPayload
     */
    private function composeUserOutput(
        array $lastAudit,
        array $execResult,
        ?array $lastFinal,
        ?array $lastSecurity,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
    ): string {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'composeUserOutput');
        $method->setAccessible(true);

        return (string) $method->invoke(
            $service,
            $lastAudit,
            $execResult,
            $lastFinal,
            $lastSecurity,
            $modelRoute,
            $modelsResolved,
            $memPayload,
        );
    }

    #[Test]
    public function build_next_prompt_includes_file_paths_when_commands_not_run(): void
    {
        $prompt = $this->buildNextPrompt(
            ['hello-world.txt'],
            [
                'executed_lines' => [],
                'proposed_lines' => [],
                'failed_commands' => [],
                'git_restore_failed' => false,
                'summary_text' => 'Created hello-world.txt',
            ],
            [],
            [],
            'Run the relevant test suite before merge.',
            null,
        );

        $this->assertStringContainsString('hello-world.txt', $prompt);
        $this->assertStringContainsString('test suite', strtolower($prompt));
    }

    #[Test]
    public function build_next_prompt_uses_first_required_action_from_final_reviewer(): void
    {
        $prompt = $this->buildNextPrompt(
            [],
            [
                'executed_lines' => [],
                'proposed_lines' => [],
                'failed_commands' => [],
                'git_restore_failed' => false,
                'summary_text' => 'Done',
            ],
            [],
            [],
            'Run tests',
            ['required_actions' => ['Run php artisan test and paste failures']],
        );

        $this->assertSame('Run php artisan test and paste failures', $prompt);
    }

    #[Test]
    public function compose_user_output_includes_next_prompt_section(): void
    {
        $output = $this->composeUserOutput(
            ['status' => 'pass'],
            [
                'status' => 'success',
                'files_changed' => [['path' => 'hello-world.txt', 'change_type' => 'created']],
                'commands_run' => [],
                'patch_summary' => "Proposed creation of hello-world.txt with content 'Hello World'.",
            ],
            null,
            null,
            ['skill' => 'general'],
            [],
            [],
        );

        $this->assertStringContainsString('## Next recommended step', $output);
        $this->assertStringContainsString('## Next prompt', $output);
        $this->assertStringContainsString('hello-world.txt', $output);
    }
}
