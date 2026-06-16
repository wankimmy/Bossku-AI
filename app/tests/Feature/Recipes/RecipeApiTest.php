<?php

namespace Tests\Feature\Recipes;

use App\Services\Recipes\RecipeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bossku.api_auth_enabled' => false]);
    }

    #[Test]
    public function repository_loads_the_starter_recipes_from_disk(): void
    {
        $slugs = array_map(fn ($r) => $r->slug, (new RecipeRepository)->all());
        $this->assertContains('security-audit', $slugs);
        $this->assertContains('add-feature', $slugs);
    }

    #[Test]
    public function index_and_show_expose_recipes(): void
    {
        $this->getJson('/api/recipes')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'security-audit']);

        $this->getJson('/api/recipes/add-feature')
            ->assertOk()
            ->assertJsonPath('slug', 'add-feature')
            ->assertJsonPath('workflow', 'orchestrator_executor_auditor');
    }

    #[Test]
    public function preview_renders_with_params_and_scans(): void
    {
        $response = $this->postJson('/api/recipes/security-audit/preview', [
            'parameters' => ['target' => 'app/Http', 'depth' => 'full'],
        ])
            ->assertOk()
            ->assertJsonPath('errors', [])
            ->assertJsonPath('scan_severity', 'none');

        $this->assertStringContainsString('full security audit of: app/Http', $response->json('prompt'));
    }

    #[Test]
    public function preview_rejects_invalid_select(): void
    {
        $this->postJson('/api/recipes/security-audit/preview', [
            'parameters' => ['depth' => 'nuclear'],
        ])->assertStatus(422);
    }

    #[Test]
    public function unknown_recipe_is_404(): void
    {
        $this->getJson('/api/recipes/does-not-exist')->assertNotFound();
    }
}
