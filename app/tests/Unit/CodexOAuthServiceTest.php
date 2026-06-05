<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\OAuth\CodexOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CodexOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function begin_authorization_stores_pkce_verifier_in_cache(): void
    {
        $service = app(CodexOAuthService::class);
        $begin = $service->beginAuthorization();

        $this->assertNotEmpty($begin['url']);
        $this->assertNotEmpty($begin['state']);
        $this->assertStringContainsString('auth.openai.com', $begin['url']);

        $cached = Cache::get('bossku_codex_oauth:'.$begin['state']);
        $this->assertIsArray($cached);
        $this->assertNotEmpty($cached['code_verifier'] ?? null);
    }

    #[Test]
    public function handle_callback_persists_encrypted_tokens(): void
    {
        Http::fake([
            'auth.openai.com/oauth/token' => Http::response([
                'access_token' => 'access-abc',
                'refresh_token' => 'refresh-xyz',
                'expires_in' => 3600,
            ], 200),
        ]);

        $service = app(CodexOAuthService::class);
        $begin = $service->beginAuthorization();
        $service->handleCallback('auth-code', $begin['state']);

        $encrypted = Setting::getValue('codex_auth_encrypted');
        $this->assertNotNull($encrypted);

        $status = $service->status();
        $this->assertTrue($status['connected']);
    }
}
