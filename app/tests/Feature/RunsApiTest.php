<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStreamEvent;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bossku.api_auth_enabled' => false,
        ]);
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
    public function runs_stream_returns_event_stream_content_type(): void
    {
        $this->mock(OrchestratorService::class, function ($mock) {
            $mock->shouldReceive('run')
                ->once()
                ->andReturnUsing(function (string $prompt, ?callable $emit = null) {
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
