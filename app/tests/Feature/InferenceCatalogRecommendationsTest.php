<?php

namespace Tests\Feature;

use App\Models\BosskuAi\LlmProvider;
use App\Models\BosskuAi\ModelRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InferenceCatalogRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function catalog_returns_cloud_providers_structure(): void
    {
        $this->getJson('/api/settings/inference-catalog')
            ->assertOk()
            ->assertJsonPath('cloud_only', true)
            ->assertJsonStructure([
                'providers' => [[
                    'provider', 'name', 'auth', 'configured',
                    'all_cloud_models', 'recommended_models',
                ]],
            ]);
    }

    #[Test]
    public function recommendations_filtered_by_role_and_provider(): void
    {
        $this->getJson('/api/settings/model-recommendations?role=executor&provider=moonshot')
            ->assertOk()
            ->assertJsonStructure([
                'role', 'provider', 'recommended_models', 'auto_selected',
            ])
            ->assertJsonPath('role', 'executor')
            ->assertJsonPath('provider', 'moonshot');
    }

    #[Test]
    public function model_router_resolves_planner_route_via_orchestrator_alias(): void
    {
        $provider = LlmProvider::create([
            'name' => 'Test Anthropic',
            'slug' => 'anthropic',
            'type' => 'anthropic',
            'base_url' => 'https://api.anthropic.com',
            'is_active' => true,
        ]);

        ModelRoute::create([
            'role' => 'planner',
            'primary_provider_id' => $provider->id,
            'primary_model' => 'claude-opus-4-8',
            'is_active' => true,
        ]);

        $this->assertTrue(
            \App\Models\BosskuAi\ModelRoute::where('role', 'planner')->where('is_active', true)->exists()
        );

        $variants = \App\Services\Llm\RoleAliasHelper::variants('orchestrator');
        $this->assertContains('planner', $variants);
    }

    #[Test]
    public function provider_presets_endpoint_returns_cloud_providers(): void
    {
        $this->getJson('/api/providers/presets')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'deepseek'])
            ->assertJsonFragment(['slug' => 'moonshot']);
    }

    #[Test]
    public function codex_oauth_provider_rejected_on_store(): void
    {
        $this->postJson('/api/providers', [
            'name' => 'Codex',
            'slug' => 'codex-test',
            'type' => 'codex_oauth',
        ])->assertStatus(422);
    }
}
