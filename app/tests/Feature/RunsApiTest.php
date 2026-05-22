<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
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
