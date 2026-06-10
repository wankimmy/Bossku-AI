<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiAuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_is_open_when_auth_disabled(): void
    {
        config([
            'bossku.api_auth_enabled' => false,
            'bossku.api_token' => 'secret-token',
        ]);

        $this->getJson('/api/dashboard')->assertOk();
    }

    #[Test]
    public function api_requires_token_when_auth_enabled(): void
    {
        config([
            'bossku.api_auth_enabled' => true,
            'bossku.api_token' => 'secret-token',
        ]);

        $this->getJson('/api/dashboard')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->getJson('/api/dashboard')
            ->assertOk();
    }

    #[Test]
    public function health_and_oauth_callback_stay_public_when_auth_enabled(): void
    {
        config([
            'bossku.api_auth_enabled' => true,
            'bossku.api_token' => 'secret-token',
        ]);

        // The health endpoint performs a live Ollama round-trip and may return
        // 503 when no Ollama is reachable (as in CI). This test only guards the
        // auth bypass: public routes must never come back 401/403.
        $health = $this->getJson('/api/health/ollama');
        $this->assertNotContains($health->status(), [401, 403]);

        $this->get('/api/oauth/codex/callback')->assertRedirect();
    }
}
