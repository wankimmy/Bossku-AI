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
    public function learning_accept_sets_status_to_accepted(): void
    {
        $event = LearningEvent::create([
            'type'    => 'pattern',
            'content' => 'Always validate inputs.',
            'status'  => 'pending',
        ]);

        $response = $this->postJson("/api/learning/{$event->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('event.status', 'accepted');

        $this->assertDatabaseHas('bossku_ai_learning_events', [
            'id'     => $event->id,
            'status' => 'accepted',
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
