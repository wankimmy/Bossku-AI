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

        $this->getJson('/api/health/ollama')->assertOk();
        $this->get('/api/oauth/codex/callback')->assertRedirect();
    }
}
