<?php

namespace Tests\Feature;

use App\Models\BosskuAi\LearningEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function learning_index_returns_200(): void
    {
        $response = $this->getJson('/api/learning');

        $response->assertStatus(200);
    }

    /** @test */
    public function learning_accept_promotes_to_memory_and_applies(): void
    {
        $event = LearningEvent::create([
            'type'       => 'correction',
            'content'    => 'Always validate inputs.',
            'confidence' => 0.9,
            'status'     => 'pending',
        ]);

        $response = $this->postJson("/api/learning/{$event->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('event.status', 'applied')
            ->assertJsonStructure(['memory_id']);

        $memoryId = $response->json('memory_id');
        $this->assertNotNull($memoryId);
        $this->assertDatabaseHas('bossku_ai_memories', [
            'id'     => $memoryId,
            'type'   => 'learned_correction',
            'source' => 'learning_event',
        ]);
        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'id'     => $event->id,
            'status' => 'applied',
        ]);
    }

    /** @test */
    public function learning_reject_sets_status_to_rejected(): void
    {
        $event = LearningEvent::create([
            'type'    => 'pattern',
            'content' => 'Use raw SQL everywhere.',
            'status'  => 'pending',
        ]);

        $response = $this->postJson("/api/learning/{$event->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('event.status', 'rejected');

        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'id'     => $event->id,
            'status' => 'rejected',
        ]);
    }
}
