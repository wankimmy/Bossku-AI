<?php

namespace Tests\Unit;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Orchestrator\ExecutorService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecutorSpecialistContextTest extends TestCase
{
    #[Test]
    public function executor_prompt_includes_specialist_handoff_when_provided(): void
    {
        $captured = [];

        $fallback = $this->mock(ModelFallbackService::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('chatWithFallbacks')
                ->once()
                ->withArgs(function (
                    array $models,
                    array $messages,
                    float $temperature,
                    int $retryCount,
                    string $role
                ) use (&$captured): bool {
                    $captured = compact('models', 'messages', 'temperature', 'retryCount', 'role');

                    return $role === 'executor';
                })
                ->andReturn([
                    'parsed' => [
                        'status' => 'success',
                        'files_read' => [],
                        'files_changed' => [],
                        'commands_run' => [],
                        'tests_run' => [],
                        'tests_result' => 'not_run',
                        'patch_summary' => 'Done',
                        'known_issues' => [],
                        'needs_user_input' => false,
                        'blockers' => [],
                        'suggested_options' => [],
                        'needs_audit' => true,
                        'handoff_message' => 'Ready for audit',
                        'executor_questions' => [],
                        'memory_lessons_applied' => [],
                        'checklist_status' => [],
                    ],
                    'model_used' => 'qwen3-coder',
                    'model_resolved' => 'qwen3-coder',
                    'provider_used' => 'ollama',
                    'fallback_used' => false,
                    'fallback_reason' => null,
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                ]);
        });

        $routing = $this->mock(ModelRoutingConfig::class, function ($mock) {
            $mock->shouldReceive('executorProfile')->once()->andReturn([
                'primary' => 'qwen3-coder',
                'fallback' => [],
                'retry_count' => 0,
                'temperature' => 0.2,
                'max_tokens' => 4096,
            ]);
            $mock->shouldReceive('executorRules')->once()->andReturn([]);
        });

        $personas = $this->mock(AgentPersonaService::class, function ($mock) {
            $mock->shouldReceive('wrapHandoffUserContent')
                ->once()
                ->andReturnUsing(fn (string $toRole, ?string $fromRole, ?string $handoff, string $payload) => $payload);
        });

        $service = new ExecutorService($fallback, $routing, $personas);
        $service->execute(
            ['id' => 1, 'task' => 'Fix checkout totals', 'skill' => 'checkout'],
            ['name' => 'checkout', 'content' => ''],
            [],
            '',
            '',
            null,
            ['summary' => 'Fix totals'],
            ['workflow' => 'orchestrator_executor_auditor'],
            'default',
            'Active project context',
            [],
            null,
            [],
            [],
            'run-1',
            [
                'agent' => ['display_name' => 'Checkout Specialist'],
                'handoff' => ['handoff_to_executor' => 'Inspect fee rounding and total formatting before edits.'],
            ],
        );

        $payload = (string) ($captured['messages'][1]['content'] ?? '');
        $this->assertStringContainsString('Specialist agent handoff', $payload);
        $this->assertStringContainsString('Checkout Specialist', $payload);
        $this->assertStringContainsString('Inspect fee rounding', $payload);
    }
}
