<?php

namespace Tests\Unit;

use App\Models\BosskuAi\LearningEvent;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Services\Learning\LearningEventPromoter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LearningEventPromoterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_promotes_event_to_memory_and_marks_applied(): void
    {
        $run = Run::factory()->create(['status' => 'completed']);
        $event = LearningEvent::create([
            'run_id' => $run->id,
            'type' => 'correction',
            'content' => 'Always validate API inputs.',
            'confidence' => 0.9,
            'status' => 'pending',
        ]);

        $memory = app(LearningEventPromoter::class)->promote($event, 'manual');

        $this->assertNotNull($memory);
        $this->assertDatabaseHas('bossku_ai_memories', [
            'id' => $memory->id,
            'type' => 'learned_correction',
            'source' => 'learning_event',
        ]);
        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'id' => $event->id,
            'status' => 'applied',
        ]);
        $this->assertDatabaseHas('bossku_ai_memory_run_links', [
            'memory_id' => $memory->id,
            'run_id' => $run->id,
        ]);

        $event->refresh();
        $this->assertSame($memory->id, $event->metadata['memory_id'] ?? null);
        $this->assertSame('manual', $event->metadata['promotion_mode'] ?? null);
    }

    #[Test]
    public function it_is_idempotent_when_already_applied(): void
    {
        $memory = Memory::query()->create([
            'type' => 'learned_pattern',
            'content' => '{}',
            'human_summary' => 'test',
            'metadata' => ['learning_event_id' => 'evt-1'],
            'tags' => ['learning'],
            'source' => 'learning_event',
            'is_active' => true,
            'confidence' => 0.9,
        ]);

        $event = LearningEvent::create([
            'type' => 'pattern',
            'content' => 'Use repositories.',
            'confidence' => 0.9,
            'status' => 'applied',
            'metadata' => ['memory_id' => $memory->id],
        ]);

        $result = app(LearningEventPromoter::class)->promote($event, 'auto');

        $this->assertSame($memory->id, $result?->id);
        $this->assertSame(1, Memory::query()->count());
    }

    #[Test]
    public function it_skips_rejected_events(): void
    {
        $event = LearningEvent::create([
            'type' => 'pattern',
            'content' => 'Bad advice.',
            'confidence' => 0.9,
            'status' => 'rejected',
        ]);

        $result = app(LearningEventPromoter::class)->promote($event, 'manual');

        $this->assertNull($result);
        $this->assertSame(0, Memory::query()->count());
    }
}
