<?php

namespace Tests\Feature;

use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\LearningEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackLearningTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function thumbs_down_feedback_creates_learning_event(): void
    {
        $response = $this->postJson('/api/feedback', [
            'target_type' => 'run',
            'target_id'   => '00000000-0000-4000-8000-000000000001',
            'signal'      => 'thumbs_down',
            'comment'     => 'Output missed the migration step.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'type'    => 'correction',
            'status'  => 'pending',
            'content' => 'Output missed the migration step.',
        ]);

        $item = FeedbackItem::query()->first();
        $this->assertNotNull($item);
        $this->assertTrue($item->processed);

        $event = LearningEvent::query()->where('type', 'correction')->first();
        $this->assertNotNull($event);
        $this->assertSame(
            $item->id,
            is_array($event->evidence) ? ($event->evidence['feedback_item_id'] ?? null) : null,
        );
    }
}
