<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStreamEvent;
use App\Models\BosskuAi\Project;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsApiTest extends TestCase
{
    use RefreshDatabase;

    private ?string $longPromptRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bossku.api_auth_enabled' => false,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->longPromptRoot !== null && is_dir($this->longPromptRoot)) {
            File::deleteDirectory($this->longPromptRoot);
        }

        parent::tearDown();
    }

    private function createActiveProjectRoot(): string
    {
        $this->longPromptRoot = sys_get_temp_dir().'/bossku_runs_api_long_prompt_'.uniqid();
        File::ensureDirectoryExists($this->longPromptRoot);

        Project::query()->create([
            'name' => 'Runs API Long Prompt Project',
            'host_path' => 'C:\\Users\\Safwan Hakim\\Documents\\Safwan\\RunsApiLongPrompt',
            'container_path' => $this->longPromptRoot,
            'is_active' => true,
        ]);

        return $this->longPromptRoot;
    }

    #[Test]
    public function runs_index_returns_paginated_list(): void
    {
        Run::factory()->count(2)->create();

        $this->getJson('/api/runs')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page']);
    }

    #[Test]
    public function runs_store_returns_orchestrator_result(): void
    {
        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturn([
                    'run_id' => '00000000-0000-0000-0000-000000000001',
                    'final_output' => 'Done.',
                    'routing' => ['risk_level' => 'low'],
                ]);
        });

        $this->postJson('/api/runs', ['prompt' => 'Hello audit'])
            ->assertOk()
            ->assertJsonPath('run_id', '00000000-0000-0000-0000-000000000001')
            ->assertJsonPath('final_output', 'Done.');
    }

    #[Test]
    public function prompt_at_inline_limit_runs_without_temp_files(): void
    {
        $root = $this->createActiveProjectRoot();
        $prompt = str_repeat('A', 50000);

        $this->mock(OrchestratorService::class, function ($mock) use ($prompt) {
            $mock->shouldReceive('run')
                ->once()
                ->with($prompt, null, [])
                ->andReturnUsing(function (string $received) {
                    Run::factory()->create([
                        'id' => '00000000-0000-0000-0000-000000000111',
                        'prompt' => $received,
                        'status' => 'completed',
                    ]);

                    return [
                        'run_id' => '00000000-0000-0000-0000-000000000111',
                        'final_output' => 'Done.',
                        'routing' => ['risk_level' => 'low'],
                    ];
                });
        });

        $this->postJson('/api/runs', ['prompt' => $prompt])
            ->assertOk()
            ->assertJsonPath('run_id', '00000000-0000-0000-0000-000000000111');

        $this->assertDirectoryDoesNotExist($root.'/tmp/bossku-prompts');
    }

    #[Test]
    public function oversized_prompt_is_materialized_before_orchestrator_and_cleaned_after_completion(): void
    {
        $root = $this->createActiveProjectRoot();
        $prompt = 'Please analyze this oversized prompt.'."\n".str_repeat('B', 50001)."\nEND_MARKER";
        $capturedPrompt = null;

        $this->mock(OrchestratorService::class, function ($mock) use (&$capturedPrompt) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturnUsing(function (string $received, ?callable $emit = null, array $conversation = [], array $options = []) use (&$capturedPrompt) {
                    $capturedPrompt = $received;
                    Run::factory()->create([
                        'id' => '00000000-0000-0000-0000-000000000112',
                        'prompt' => $received,
                        'status' => 'completed',
                        'metadata' => [],
                    ]);

                    return [
                        'run_id' => '00000000-0000-0000-0000-000000000112',
                        'final_output' => 'Done.',
                        'routing' => ['risk_level' => 'low'],
                    ];
                });
        });

        $this->postJson('/api/runs', ['prompt' => $prompt])
            ->assertOk()
            ->assertJsonPath('run_id', '00000000-0000-0000-0000-000000000112');

        $this->assertIsString($capturedPrompt);
        $this->assertLessThan(50000, strlen($capturedPrompt));
        $this->assertStringContainsString('Long prompt attached', $capturedPrompt);
        $this->assertStringContainsString('chunks/chunk-001.md', $capturedPrompt);
        $this->assertStringNotContainsString(str_repeat('B', 50001), $capturedPrompt);

        $run = Run::query()->findOrFail('00000000-0000-0000-0000-000000000112');
        $longPrompt = $run->metadata['long_prompt'] ?? null;
        $this->assertIsArray($longPrompt);
        $this->assertSame(strlen($prompt), $longPrompt['original_length']);
        $this->assertSame('deleted', $longPrompt['cleanup_status']);
        $this->assertDirectoryDoesNotExist($root.'/'.$longPrompt['relative_dir']);
    }

    #[Test]
    public function oversized_prompt_over_one_megabyte_is_rejected(): void
    {
        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson('/api/runs', ['prompt' => str_repeat('C', 1_048_577)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    #[Test]
    public function oversized_prompt_requires_active_mounted_project(): void
    {
        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson('/api/runs', ['prompt' => str_repeat('D', 50001)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'BosskuAI cannot create a long-prompt temp file because no active mounted project is available.');
    }

    #[Test]
    public function runs_stream_post_validation_returns_json_when_accept_is_event_stream(): void
    {
        $this->post('/api/runs/stream', [], [
            'Accept' => 'text/event-stream',
            'Content-Type' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    #[Test]
    public function runs_stream_post_materializes_oversized_prompt_and_emits_temp_file_events(): void
    {
        $root = $this->createActiveProjectRoot();
        $prompt = 'Stream this long prompt.'."\n".str_repeat('E', 50001);

        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturnUsing(function (string $received, ?callable $emit = null, array $conversation = [], array $options = []) {
                    Run::factory()->create([
                        'id' => '00000000-0000-0000-0000-000000000113',
                        'prompt' => $received,
                        'status' => 'completed',
                        'metadata' => [],
                    ]);

                    if ($emit !== null) {
                        $emit(['type' => 'run_completed', 'run_id' => '00000000-0000-0000-0000-000000000113', 'status' => 'success', 'output' => 'Done.']);
                    }

                    return ['run_id' => '00000000-0000-0000-0000-000000000113'];
                });
        });

        $response = $this->call('POST', '/api/runs/stream', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'text/event-stream',
        ], json_encode([
            'prompt' => $prompt,
            'conversation' => [],
        ]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('long_prompt_materialized', $content);
        $this->assertStringContainsString('long_prompt_cleaned', $content);

        $run = Run::query()->findOrFail('00000000-0000-0000-0000-000000000113');
        $longPrompt = $run->metadata['long_prompt'] ?? null;
        $this->assertIsArray($longPrompt);
        $this->assertSame('deleted', $longPrompt['cleanup_status']);
        $this->assertDirectoryDoesNotExist($root.'/'.$longPrompt['relative_dir']);
    }

    #[Test]
    public function runs_stream_returns_event_stream_content_type(): void
    {
        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturnUsing(function (string $prompt, ?callable $emit = null) {
                    Run::factory()->create([
                        'id' => '00000000-0000-0000-0000-000000000099',
                        'prompt' => $prompt,
                        'status' => 'completed',
                    ]);

                    if ($emit !== null) {
                        $emit(['type' => 'run_completed', 'run_id' => '00000000-0000-0000-0000-000000000099']);
                    }

                    return ['run_id' => '00000000-0000-0000-0000-000000000099'];
                });
        });

        $response = $this->get('/api/runs/stream?prompt=hello');

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('data:', $response->streamedContent());
    }

    #[Test]
    public function runs_stream_events_endpoint_returns_persisted_events(): void
    {
        $runId = '00000000-0000-0000-0000-000000000088';

        $this->mock(OrchestratorService::class, function ($mock) use ($runId) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturnUsing(function (string $prompt, ?callable $emit = null) use ($runId) {
                    if ($emit !== null) {
                        $emit(['type' => 'run_started', 'run_id' => $runId, 'status' => 'success']);
                        $emit(['type' => 'run_completed', 'run_id' => $runId, 'status' => 'success', 'output' => 'Done.']);
                    }

                    return ['run_id' => $runId];
                });
        });

        Run::factory()->create(['id' => $runId, 'status' => 'running']);

        $response = $this->get('/api/runs/stream?prompt=hello');
        $response->assertOk();
        $response->streamedContent();

        $this->getJson("/api/runs/{$runId}/stream-events?after_seq=0")
            ->assertOk()
            ->assertJsonPath('run_id', $runId)
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('last_seq', 2);

        $this->getJson("/api/runs/{$runId}/stream-events?after_seq=1")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.type', 'run_completed');
    }

    #[Test]
    public function runs_stream_events_persist_without_live_client(): void
    {
        $runId = '00000000-0000-0000-0000-000000000077';

        Run::factory()->create(['id' => $runId, 'status' => 'completed']);

        RunStreamEvent::query()->create([
            'run_id' => $runId,
            'seq' => 1,
            'payload' => ['type' => 'run_started', 'run_id' => $runId],
            'created_at' => now(),
        ]);
        RunStreamEvent::query()->create([
            'run_id' => $runId,
            'seq' => 2,
            'payload' => ['type' => 'run_completed', 'run_id' => $runId, 'output' => 'ok'],
            'created_at' => now(),
        ]);

        $this->getJson("/api/runs/{$runId}/stream-events")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('last_seq', 2);
    }

    #[Test]
    public function stream_events_unknown_run_returns_json_404(): void
    {
        $missingId = '00000000-0000-4000-8000-000000000000';

        $this->getJson("/api/runs/{$missingId}")
            ->assertStatus(404)
            ->assertJsonPath('run_id', $missingId)
            ->assertJsonPath('message', 'Run not found. It may have been removed or never created—start a new task and try again.');

        $this->getJson("/api/runs/{$missingId}/stream-events?after_seq=0")
            ->assertStatus(404)
            ->assertJsonPath('run_id', $missingId);
    }

    #[Test]
    public function runs_require_token_when_auth_enabled(): void
    {
        config([
            'bossku.api_auth_enabled' => true,
            'bossku.api_token' => 'run-test-token',
        ]);

        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')->never();
        });

        $this->postJson('/api/runs', ['prompt' => 'blocked'])->assertUnauthorized();

        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn([
                'run_id' => '00000000-0000-0000-0000-000000000002',
                'final_output' => 'ok',
            ]);
        });

        $this->withHeader('Authorization', 'Bearer run-test-token')
            ->postJson('/api/runs', ['prompt' => 'allowed'])
            ->assertOk();
    }
}
