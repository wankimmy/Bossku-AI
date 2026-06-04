<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\UsageEvent;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageTrackerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_usage_for_an_existing_run(): void
    {
        $run = Run::factory()->create(['status' => 'running']);
        $tracker = app(UsageTracker::class);

        $event = $tracker->track(
            LlmRequest::make('kimi-k2.6', [], ['role' => 'clarification', 'run_id' => $run->id]),
            new LlmResponse(
                text: 'ok',
                provider: 'ollama',
                modelLogical: 'kimi-k2.6',
                modelResolved: 'kimi-k2.6:cloud',
                inputTokens: 10,
                outputTokens: 20,
            ),
        );

        $this->assertNotNull($event);
        $this->assertDatabaseHas('bossku_ai_usage_events', [
            'id' => $event->id,
            'run_id' => $run->id,
            'role' => 'clarification',
        ]);
    }

    #[Test]
    public function it_records_usage_without_run_when_parent_run_is_missing(): void
    {
        $missingRunId = (string) Str::uuid();
        $tracker = app(UsageTracker::class);

        $event = $tracker->track(
            LlmRequest::make('kimi-k2.6', [], ['role' => 'clarification', 'run_id' => $missingRunId]),
            new LlmResponse(
                text: 'ok',
                provider: 'ollama',
                modelLogical: 'kimi-k2.6',
                modelResolved: 'kimi-k2.6:cloud',
                inputTokens: 5,
                outputTokens: 7,
            ),
        );

        $this->assertNotNull($event);
        $this->assertNull($event->run_id);
        $this->assertSame($missingRunId, $event->metadata['run_id_orphaned'] ?? null);
        $this->assertSame(1, UsageEvent::query()->whereNull('run_id')->where('role', 'clarification')->count());
    }
}
