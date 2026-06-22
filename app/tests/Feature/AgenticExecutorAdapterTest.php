<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Services\Agents\AgenticExecutorAdapter;
use App\Services\Agents\AgenticToolLoop;
use App\Services\Agents\AgentToolPermissionService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgenticExecutorAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function adapterWithModel(callable $responder): AgenticExecutorAdapter
    {
        $fake = new class extends ModelFallbackService
        {
            /** @var callable */
            public $responder;

            public int $calls = 0;

            public function __construct() {}

            public function chatWithFallbacks(array $models, array $messages, float $temperature, int $retryCount, string $role, ?callable $isValidJson = null, ?int $maxTokensAnthropic = null, ?string $runId = null, ?string $runStepId = null, array $metadata = []): array
            {
                $parsed = ($this->responder)($this->calls++);

                return [
                    'text' => json_encode($parsed) ?: '{}',
                    'parsed' => $parsed,
                    'model_used' => 'fake-model',
                    'model_resolved' => 'fake',
                    'provider_used' => 'fake',
                    'fallback_used' => false,
                    'fallback_reason' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ];
            }
        };
        $fake->responder = $responder;

        $loop = new AgenticToolLoop(
            $fake,
            app(ToolRegistry::class),
            app(AgentToolPermissionService::class),
            app(ModelRoutingConfig::class),
        );

        return new AgenticExecutorAdapter($loop);
    }

    #[Test]
    public function builds_executor_result_from_loop_actions(): void
    {
        $run = Run::query()->create(['prompt' => 'agentic test', 'status' => 'running', 'metadata' => []]);

        $name = 'ae_'.uniqid().'.txt';
        $abs = storage_path('framework/testing/'.$name);
        $rel = 'app/storage/framework/testing/'.$name;
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, "one\ntwo\nthree\n");

        $adapter = $this->adapterWithModel(fn (int $i) => match ($i) {
            0 => ['tool_calls' => [
                ['tool' => 'file_edit', 'payload' => ['path' => $rel, 'old_string' => 'two', 'new_string' => 'TWO']],
                ['tool' => 'run_command', 'payload' => ['command' => 'git status']],
            ], 'done' => false],
            default => ['done' => true, 'final' => ['summary' => 'edited the file and checked status']],
        });

        try {
            $result = $adapter->execute(
                ['id' => 1, 'task' => 'rename two to TWO'],
                ['summary' => 'do it', 'target_file_list' => [$rel]],
                [],
                'default',
                (string) $run->id,
            );
        } finally {
            @unlink($abs);
        }

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['_agentic']);
        $this->assertTrue($result['_files_already_applied']);
        $this->assertTrue($result['_commands_already_run']);
        $this->assertSame('edited the file and checked status', $result['handoff_message']);

        $changedPaths = array_column($result['files_changed'], 'path');
        $this->assertContains($rel, $changedPaths);

        $commands = array_column($result['commands_run'], 'command');
        $this->assertContains('git status', $commands);
    }

    #[Test]
    public function maps_stuck_loop_to_partial_status(): void
    {
        $run = Run::query()->create(['prompt' => 'stuck test', 'status' => 'running', 'metadata' => []]);

        $adapter = $this->adapterWithModel(fn (int $i) => [
            'tool_calls' => [['tool' => 'log', 'payload' => ['message' => 'same']]],
            'done' => false,
        ]);

        $result = $adapter->execute(['id' => 1, 'task' => 'spin'], ['summary' => 's'], [], 'default', (string) $run->id);

        $this->assertSame('partial', $result['status']);
        $this->assertSame('stuck', $result['_agentic_status']);
        $this->assertNotEmpty($result['known_issues']);
    }
}
