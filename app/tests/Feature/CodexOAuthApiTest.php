<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CodexOAuthApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function codex_status_reports_disconnected_by_default(): void
    {
        $this->getJson('/api/oauth/codex/status')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('configured', true);
    }

    #[Test]
    public function codex_disconnect_clears_connection(): void
    {
        $this->deleteJson('/api/oauth/codex')
            ->assertOk()
            ->assertJsonPath('connected', false);
    }

    #[Test]
    public function codex_authorize_redirects_to_openai(): void
    {
        $response = $this->get('/api/oauth/codex/authorize');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('auth.openai.com', $location);
        $this->assertStringContainsString('code_challenge', $location);
    }
}
