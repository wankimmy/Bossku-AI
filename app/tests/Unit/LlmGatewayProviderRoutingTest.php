<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\RuntimeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class LlmGatewayProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function claude_model_routes_to_anthropic_when_key_configured(): void
    {
        Setting::setValue('anthropic_api_key_encrypted', Crypt::encryptString('sk-ant-test'));

        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('anthropic', $gw->resolveProviderForModel('claude-sonnet-4-5'));
    }

    #[Test]
    public function claude_model_without_key_throws_clear_error(): void
    {
        config(['bossku_models.aliases' => []]);

        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Anthropic API key is required');

        $gw->resolveProviderForModel('claude-sonnet-4-5');
    }

    #[Test]
    public function gpt_model_routes_to_codex_when_connected(): void
    {
        $payload = json_encode([
            'access_token' => 'tok',
            'refresh_token' => 'ref',
            'expires_at' => time() + 3600,
            'last_refresh' => now()->toIso8601String(),
            'auth_mode' => 'chatgpt',
        ], JSON_THROW_ON_ERROR);
        Setting::setValue('codex_auth_encrypted', Crypt::encryptString($payload));

        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('codex', $gw->resolveProviderForModel('gpt-4o'));
    }

    #[Test]
    public function anthropic_env_fallback_counts_as_configured(): void
    {
        $this->app->instance(RuntimeSettings::class, new class extends RuntimeSettings
        {
            public function anthropicApiKey(): ?string
            {
                return 'sk-ant-from-env';
            }
        });

        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('anthropic', $gw->resolveProviderForModel('claude-haiku-4-5'));
    }
}
