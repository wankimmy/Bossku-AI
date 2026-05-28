<?php

namespace Tests\Unit;

use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;
use App\Services\Llm\ModelRegistry;
use App\Services\Llm\ModelRouter;
use App\Services\Llm\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(string $slug): LlmProviderInterface
    {
        return new class ($slug) implements LlmProviderInterface {
            public function __construct(private string $slug) {}

            public function getSlug(): string
            {
                return $this->slug;
            }

            public function complete(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(
                    text: 'ok',
                    provider: $this->slug,
                    modelLogical: $request->model,
                    modelResolved: $request->model,
                    inputTokens: 0,
                    outputTokens: 0,
                    costUsd: 0.0,
                );
            }

            public function stream(LlmRequest $request): iterable
            {
                return [];
            }

            public function listModels(): array
            {
                return [];
            }

            public function healthCheck(): ProviderHealthStatus
            {
                return new ProviderHealthStatus(
                    provider: $this->slug,
                    status: 'healthy',
                    latencyMs: 0,
                );
            }

            public function estimateCost(LlmRequest $request): CostEstimate
            {
                return new CostEstimate(
                    estimatedUsd: 0.0,
                    estimatedInputTokens: 0,
                    estimatedOutputTokens: 0,
                    provider: $this->slug,
                    model: $request->model,
                );
            }
        };
    }

    private function makeRouter(): ModelRouter
    {
        /** @var UsageTracker $tracker */
        $tracker = $this->createMock(UsageTracker::class);

        return new ModelRouter($tracker);
    }

    #[Test]
    public function register_and_resolve_with_force_provider_returns_correct_provider(): void
    {
        $router = $this->makeRouter();

        $openaiProvider = $this->makeProvider('openai');
        $router->registerProvider($openaiProvider);

        $request = new LlmRequest(
            model: 'gpt-4o',
            messages: [['role' => 'user', 'content' => 'Hello']],
            forceProvider: 'openai',
        );

        $result = $router->resolve($request);

        $this->assertSame('openai', $result['provider']->getSlug());
        $this->assertSame('gpt-4o', $result['model']);
    }

    #[Test]
    public function resolve_with_unknown_force_provider_throws_runtime_exception(): void
    {
        $router = $this->makeRouter();

        $request = new LlmRequest(
            model: 'gpt-4o',
            messages: [['role' => 'user', 'content' => 'Hello']],
            forceProvider: 'nonexistent-provider',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/nonexistent-provider/');

        $router->resolve($request);
    }

    #[Test]
    public function resolve_with_no_route_falls_back_to_ollama_provider(): void
    {
        $router = $this->makeRouter();

        $ollamaProvider = $this->makeProvider('ollama');
        $router->registerProvider($ollamaProvider);

        $request = new LlmRequest(
            model: 'llama3.1',
            messages: [['role' => 'user', 'content' => 'Hello']],
            role: 'unregistered-role-xyz',
        );

        $result = $router->resolve($request);

        $this->assertSame('ollama', $result['provider']->getSlug());
    }

    #[Test]
    public function normalize_slug_maps_ollama_cloud_to_ollama(): void
    {
        $router = $this->makeRouter();

        $this->assertSame('ollama', $router->normalizeProviderSlug('ollama-cloud'));
        $this->assertSame('ollama', $router->normalizeProviderSlug('ollama-local'));
    }

    #[Test]
    public function resolve_uses_fallback_model_when_primary_provider_unregistered(): void
    {
        $router = $this->makeRouter();
        $ollama = $this->makeProvider('ollama');
        $router->registerProvider($ollama);

        $primary = \App\Models\BosskuAi\LlmProvider::create([
            'name' => 'Ghost',
            'slug' => 'not-registered-slug',
            'type' => 'custom',
            'is_active' => true,
        ]);
        $fallback = \App\Models\BosskuAi\LlmProvider::create([
            'name' => 'Ollama Cloud',
            'slug' => 'ollama-cloud',
            'type' => 'ollama',
            'is_active' => true,
        ]);

        \App\Models\BosskuAi\ModelRoute::create([
            'role' => 'executor',
            'primary_provider_id' => $primary->id,
            'primary_model' => 'primary-model',
            'fallback_provider_id' => $fallback->id,
            'fallback_model' => 'fallback-model:cloud',
            'is_active' => true,
        ]);

        $result = $router->resolve(new LlmRequest(
            model: 'request-model',
            messages: [['role' => 'user', 'content' => 'hi']],
            role: 'executor',
        ));

        $this->assertSame('ollama', $result['provider']->getSlug());
        $this->assertSame('fallback-model:cloud', $result['model']);
    }
}
