<?php

namespace Tests\Unit;

use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Services\Learning\LearningEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LearningEventProcessorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function auto_eligible_pattern_dispatches_job(): void
    {
        config(['bossku.learning_require_verification' => false]);
        Queue::fake();

        $event = LearningEvent::create([
            'type' => 'pattern',
            'content' => 'Task succeeded with audit pass.',
            'confidence' => 0.9,
            'status' => 'pending',
        ]);

        app(LearningEventProcessor::class)->dispatchForEvent($event);

        Queue::assertPushed(\App\Jobs\ProcessLearningEventJob::class, function ($job) use ($event) {
            return $job->learningEventId === $event->id;
        });
    }

    #[Test]
    public function correction_does_not_auto_dispatch(): void
    {
        Queue::fake();

        $event = LearningEvent::create([
            'type' => 'correction',
            'content' => 'Do not skip tests.',
            'confidence' => 0.95,
            'status' => 'pending',
        ]);

        app(LearningEventProcessor::class)->dispatchForEvent($event);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function batch_processes_only_auto_eligible_pending_events(): void
    {
        config(['bossku.learning_require_verification' => false]);
        $auto = LearningEvent::create([
            'type' => 'preference',
            'content' => 'User prefers concise answers.',
            'confidence' => 0.88,
            'status' => 'pending',
        ]);
        LearningEvent::create([
            'type' => 'failure',
            'content' => 'Build failed.',
            'confidence' => 0.95,
            'status' => 'pending',
        ]);

        $result = app(LearningEventProcessor::class)->processPendingBatch(10);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'id' => $auto->id,
            'status' => 'applied',
        ]);
        $this->assertGreaterThanOrEqual(1, Memory::query()->count());
    }

    #[Test]
    public function verification_required_blocks_auto_promote_without_verified_feedback(): void
    {
        config(['bossku.learning_require_verification' => true]);

        $processor = app(LearningEventProcessor::class);
        $event = LearningEvent::create([
            'type' => 'pattern',
            'content' => 'Unverified pattern.',
            'confidence' => 0.95,
            'status' => 'pending',
        ]);

        $this->assertFalse($processor->isAutoPromoteEligible($event));
    }

    #[Test]
    public function low_confidence_pattern_is_not_auto_eligible(): void
    {
        config(['bossku.learning_require_verification' => false]);
        $processor = app(LearningEventProcessor::class);

        $event = LearningEvent::create([
            'type' => 'pattern',
            'content' => 'Weak signal.',
            'confidence' => 0.5,
            'status' => 'pending',
        ]);

        $this->assertFalse($processor->isAutoPromoteEligible($event));
    }
}
