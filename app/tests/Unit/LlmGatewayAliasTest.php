<?php

namespace Tests\Unit;

use App\Services\BosskuAi\LlmGateway;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class LlmGatewayAliasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var array<string, string> $aliases */
        $aliases = config('bossku_models.aliases', []);
        $aliases['gpt-5.5'] = 'kimi-k2.6:cloud';
        $aliases['claude-opus-4.7'] = 'deepseek-v4-pro:cloud';
        config(['bossku_models.aliases' => $aliases]);
    }

    #[Test]
    public function resolves_legacy_gpt_logical_via_alias_to_ollama(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('ollama', $gw->resolveProvider('gpt-5.5'));
        $resolved = strtolower($gw->resolveAlias('gpt-5.5'));
        $this->assertStringContainsString(':cloud', $resolved);
        $this->assertStringNotContainsString('anthropic.com', $resolved);
    }

    #[Test]
    public function resolves_claude_logical_via_alias_to_ollama(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('ollama', $gw->resolveProvider('claude-opus-4.7'));
        $resolved = strtolower($gw->resolveAlias('claude-opus-4.7'));
        $this->assertStringContainsString(':cloud', $resolved);
    }

    #[Test]
    public function resolves_kimi_logical_to_ollama(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('ollama', $gw->resolveProvider('kimi-k2.6'));
    }

    #[Test]
    public function resolves_gemini_logical_via_alias_to_ollama(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('ollama', $gw->resolveProvider('gemini-3-pro'));
    }

    #[Test]
    public function resolves_glm_and_deepseek_to_ollama(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->assertSame('ollama', $gw->resolveProvider('glm-5.1'));
        $this->assertSame('ollama', $gw->resolveProvider('deepseek-v4-pro'));
    }

    #[Test]
    public function unknown_model_throws(): void
    {
        /** @var LlmGateway $gw */
        $gw = app(LlmGateway::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown provider for model/i');

        $gw->resolveProvider('totally-made-up-model');
    }
}
