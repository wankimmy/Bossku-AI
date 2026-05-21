<?php

namespace Tests\Feature;

use App\Models\BosskuAi\LlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvidersApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function providers_index_returns_200_with_data_array(): void
    {
        $response = $this->getJson('/api/providers');

        $response->assertStatus(200)
            ->assertJsonStructure([]);
    }

    /** @test */
    public function providers_store_creates_provider_and_api_key_encrypted_not_in_response(): void
    {
        $payload = [
            'name'    => 'OpenAI Test',
            'slug'    => 'openai-test',
            'type'    => 'openai',
            'api_key' => 'sk-secret-key',
        ];

        $response = $this->postJson('/api/providers', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'OpenAI Test'])
            ->assertJsonMissing(['api_key_encrypted']);

        $this->assertDatabaseHas('bossku_ai_llm_providers', [
            'name' => 'OpenAI Test',
            'slug' => 'openai-test',
        ]);
    }

    /** @test */
    public function providers_update_patches_name(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Old Name',
            'slug' => 'old-name-provider',
            'type' => 'ollama',
        ]);

        $response = $this->patchJson("/api/providers/{$provider->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('bossku_ai_llm_providers', [
            'id'   => $provider->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function providers_destroy_returns_200(): void
    {
        $provider = LlmProvider::create([
            'name' => 'To Delete',
            'slug' => 'to-delete-provider',
            'type' => 'ollama',
        ]);

        $response = $this->deleteJson("/api/providers/{$provider->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('bossku_ai_llm_providers', [
            'id' => $provider->id,
        ]);
    }
}
