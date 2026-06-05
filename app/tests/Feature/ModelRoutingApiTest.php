<?php

namespace Tests\Feature;

use App\Models\BosskuAi\LlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelRoutingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function model_routes_create_and_update_with_dropdown_selected_models(): void
    {
        $primaryProvider = LlmProvider::create([
            'name' => 'Ollama Cloud',
            'slug' => 'ollama-cloud',
            'type' => 'ollama',
            'is_active' => true,
            'health_status' => 'healthy',
            'available_models' => ['kimi-k2.6', 'qwen3-coder-next'],
        ]);

        $fallbackProvider = LlmProvider::create([
            'name' => 'Anthropic Claude',
            'slug' => 'anthropic-claude',
            'type' => 'anthropic',
            'is_active' => true,
            'health_status' => 'healthy',
            'available_models' => ['claude-sonnet-4.6'],
        ]);

        $response = $this->postJson('/api/model-routes', [
            'role' => 'planner',
            'primary_provider_id' => $primaryProvider->id,
            'primary_model' => 'kimi-k2.6',
            'fallback_provider_id' => $fallbackProvider->id,
            'fallback_model' => 'claude-sonnet-4.6',
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('role', 'planner')
            ->assertJsonPath('primary_provider_id', $primaryProvider->id)
            ->assertJsonPath('primary_provider_name', 'Ollama Cloud')
            ->assertJsonPath('primary_model', 'kimi-k2.6')
            ->assertJsonPath('fallback_provider_id', $fallbackProvider->id)
            ->assertJsonPath('fallback_provider_name', 'Anthropic Claude')
            ->assertJsonPath('fallback_model', 'claude-sonnet-4.6');

        $routeId = (string) $response->json('id');

        $this->patchJson("/api/model-routes/{$routeId}", [
            'primary_model' => 'qwen3-coder-next',
            'fallback_model' => 'claude-sonnet-4.6',
        ])
            ->assertOk()
            ->assertJsonPath('primary_model', 'qwen3-coder-next');

        $this->getJson('/api/model-routes')
            ->assertOk()
            ->assertJsonPath('0.primary_provider_name', 'Ollama Cloud');
    }
}
