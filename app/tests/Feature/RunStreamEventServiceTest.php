<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStreamEvent;
use App\Services\RunStreamEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunStreamEventServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_appends_sequential_events_for_an_existing_run(): void
    {
        $run = Run::factory()->create(['status' => 'running']);
        $service = app(RunStreamEventService::class);

        $service->append($run->id, ['type' => 'run_started', 'run_id' => $run->id]);
        $service->append($run->id, ['type' => 'planner_done', 'run_id' => $run->id]);

        $this->assertDatabaseCount('bossku_ai_run_stream_events', 2);

        $seqs = RunStreamEvent::query()
            ->where('run_id', $run->id)
            ->orderBy('seq')
            ->pluck('seq')
            ->map(static fn ($seq) => (int) $seq)
            ->all();

        $this->assertSame([1, 2], $seqs);
    }

    #[Test]
    public function it_silently_skips_events_for_a_missing_run(): void
    {
        $service = app(RunStreamEventService::class);
        $missingRunId = (string) Str::uuid();

        // A run that emits a terminal event after it was deleted (e.g. by a
        // knowledge --fresh wipe) must not raise a foreign-key violation.
        $service->append($missingRunId, [
            'type' => 'run_failed',
            'status' => 'fail',
            'run_id' => $missingRunId,
        ]);

        $this->assertDatabaseCount('bossku_ai_run_stream_events', 0);
    }
}
