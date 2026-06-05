<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContinueRunMisrouteTest extends TestCase
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
    public function continue_stream_returns_409_when_run_awaits_executor_approvals(): void
    {
        $run = Run::factory()->create([
            'status' => 'awaiting_input',
            'metadata' => [
                'checkpoint' => [
                    'phase' => 'awaiting_approvals',
                    'stage' => 'executor_approvals',
                    'approval_ids' => [],
                    'pipeline' => ['user_prompt' => 'test'],
                ],
            ],
        ]);

        Approval::create([
            'run_id' => $run->id,
            'operation_type' => 'file_write',
            'operation_description' => 'Modify file: README.md',
            'risk_level' => 'low',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/continue/stream", [
            'answers' => [
                ['question_id' => 'q1', 'option_id' => 'proceed'],
            ],
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('stage', 'executor_approvals')
            ->assertJsonFragment([
                'resume_endpoint' => "/api/runs/{$run->id}/continue-approvals/stream",
            ]);

        $run->refresh();
        $this->assertSame('awaiting_input', $run->status);
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $this->assertSame('executor_approvals', $meta['checkpoint']['stage'] ?? null);
    }

    #[Test]
    public function stream_post_returns_409_when_conversation_matches_awaiting_input_run(): void
    {
        $conversation = [['role' => 'user', 'content' => 'Build the app']];
        $run = Run::factory()->create([
            'status' => 'awaiting_input',
            'metadata' => [
                'conversation' => $conversation,
                'checkpoint' => [
                    'stage' => 'executor_escalation',
                    'clarification' => [
                        'summary' => 'Need input',
                        'questions' => [['id' => 'q1', 'prompt' => 'Next step?']],
                    ],
                    'pipeline' => ['user_prompt' => 'Build the app'],
                ],
            ],
        ]);

        $response = $this->postJson('/api/runs/stream', [
            'prompt' => 'start with stage 1 first',
            'conversation' => $conversation,
        ]);

        $response->assertStatus(409)
            ->assertJsonFragment([
                'resume_endpoint' => "/api/runs/{$run->id}/continue/stream",
            ]);

        $run->refresh();
        $this->assertSame('awaiting_input', $run->status);
    }

    #[Test]
    public function clarification_endpoint_returns_null_clarification_for_executor_approvals_stage(): void
    {
        $run = Run::factory()->create([
            'status' => 'awaiting_input',
            'metadata' => [
                'checkpoint' => [
                    'stage' => 'executor_approvals',
                    'clarification' => [
                        'summary' => 'Should not surface',
                        'questions' => [],
                    ],
                    'pipeline' => [],
                ],
            ],
        ]);

        $this->getJson("/api/runs/{$run->id}/clarification")
            ->assertOk()
            ->assertJsonPath('stage', 'executor_approvals')
            ->assertJsonPath('clarification', null);
    }
}
