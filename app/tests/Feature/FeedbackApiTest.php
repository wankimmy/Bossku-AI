<?php

namespace Tests\Feature;

use App\Models\BosskuAi\FeedbackItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function feedback_index_returns_200_with_data_array(): void
    {
        $response = $this->getJson('/api/feedback');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function feedback_store_creates_feedback_item_and_returns_201(): void
    {
        $payload = [
            'target_type' => 'skill',
            'target_id'   => 'abc-123',
            'signal'      => 'thumbs_up',
            'rating'      => 5,
            'comment'     => 'Great skill!',
        ];

        $response = $this->postJson('/api/feedback', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['signal' => 'thumbs_up']);

        $this->assertDatabaseHas('bossku_ai_feedback_items', [
            'target_type' => 'skill',
            'target_id'   => 'abc-123',
            'signal'      => 'thumbs_up',
        ]);
    }

    /** @test */
    public function feedback_store_with_missing_signal_returns_422(): void
    {
        $payload = [
            'target_type' => 'skill',
            'target_id'   => 'abc-123',
            // signal is missing
        ];

        $response = $this->postJson('/api/feedback', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signal']);
    }

    /** @test */
    public function feedback_summary_returns_thumbs_up_thumbs_down_and_count(): void
    {
        $targetType = 'skill';
        $targetId   = 'skill-xyz';

        FeedbackItem::create([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'signal'      => 'thumbs_up',
        ]);

        FeedbackItem::create([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'signal'      => 'thumbs_up',
        ]);

        FeedbackItem::create([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'signal'      => 'thumbs_down',
        ]);

        $response = $this->getJson("/api/feedback/{$targetType}/{$targetId}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure(['thumbs_up', 'thumbs_down', 'count'])
            ->assertJsonFragment([
                'thumbs_up'   => 2,
                'thumbs_down' => 1,
                'count'       => 3,
            ]);
    }
}
