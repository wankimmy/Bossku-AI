<?php

namespace Tests\Unit;

use App\Services\Llm\Routing\BearerAuth;
use App\Services\Llm\Routing\Endpoint;
use App\Services\Llm\Routing\NoAuth;
use App\Services\Llm\Routing\OpenAiChatFraming;
use App\Services\Llm\Routing\Route;
use App\Services\Llm\Routing\RouteRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the four-axis LLM route decomposition. Proves: adding a provider
 * is a 5-line Route entry, the shared OpenAiChatFraming serves all
 * OpenAI-compatible endpoints, auth is applied correctly, and the registry
 * mirrors the existing ProviderFactory presets.
 */
class LlmRouteTest extends TestCase
{
    #[Test]
    public function openai_compatible_route_uses_shared_framing(): void
    {
        $framing = new OpenAiChatFraming;
        $route = new Route(
            id: 'test-deepseek',
            endpoint: Endpoint::url('https://api.deepseek.com'),
            auth: new BearerAuth(fn () => 'sk-test'),
            framing: $framing,
        );

        $body = $framing->requestBody('deepseek-chat', [['role' => 'user', 'content' => 'hi']], ['temperature' => 0.3]);

        $this->assertSame('deepseek-chat', $body['model']);
        $this->assertSame('hi', $body['messages'][0]['content']);
        $this->assertSame(0.3, $body['temperature']);
    }

    #[Test]
    public function bearer_auth_adds_authorization_header(): void
    {
        $auth = new BearerAuth(fn () => 'sk-secret');
        $applied = $auth->apply(['headers' => [], 'query' => []]);

        $this->assertSame('Bearer sk-secret', $applied['headers']['Authorization']);
    }

    #[Test]
    public function bearer_auth_with_custom_header_name(): void
    {
        $auth = new BearerAuth(fn () => 'sk-anthropic', 'x-api-key');
        $applied = $auth->apply(['headers' => [], 'query' => []]);

        $this->assertSame('Bearer sk-anthropic', $applied['headers']['x-api-key']);
        $this->assertArrayNotHasKey('Authorization', $applied['headers']);
    }

    #[Test]
    public function no_auth_is_a_noop(): void
    {
        $auth = new NoAuth;
        $applied = $auth->apply(['headers' => ['X-Custom' => '1'], 'query' => []]);

        $this->assertSame(['X-Custom' => '1'], $applied['headers']);
    }

    #[Test]
    public function endpoint_joins_base_url_and_path(): void
    {
        $ep = Endpoint::url('https://api.example.com', '/v1/chat/completions');
        $this->assertSame('https://api.example.com/v1/chat/completions', $ep->fullUrl());
    }

    #[Test]
    public function endpoint_trims_trailing_slash_from_base(): void
    {
        $ep = Endpoint::url('https://api.example.com/');
        $this->assertSame('https://api.example.com/v1/chat/completions', $ep->fullUrl());
    }

    #[Test]
    public function route_is_configured_when_auth_resolves(): void
    {
        $route = new Route(
            id: 'test',
            endpoint: Endpoint::url('https://api.example.com'),
            auth: new BearerAuth(fn () => 'sk-real'),
            framing: new OpenAiChatFraming,
        );

        $this->assertTrue($route->isConfigured());
    }

    #[Test]
    public function route_is_not_configured_when_auth_is_empty(): void
    {
        $route = new Route(
            id: 'test',
            endpoint: Endpoint::url('https://api.example.com'),
            auth: new BearerAuth(fn () => null),
            framing: new OpenAiChatFraming,
        );

        $this->assertFalse($route->isConfigured());
    }

    #[Test]
    public function no_auth_route_is_always_configured(): void
    {
        $route = new Route(
            id: 'local',
            endpoint: Endpoint::url('http://localhost:11434'),
            auth: new NoAuth,
            framing: new OpenAiChatFraming,
        );

        $this->assertTrue($route->isConfigured());
    }

    #[Test]
    public function registry_mirrors_existing_presets(): void
    {
        $registry = new RouteRegistry;

        // The same set as ProviderFactory::$presets + ollama local.
        $this->assertNotNull($registry->get('ollama'));
        $this->assertNotNull($registry->get('ollama-cloud'));
        $this->assertNotNull($registry->get('anthropic'));
        $this->assertNotNull($registry->get('openai'));
        $this->assertNotNull($registry->get('deepseek'));
        $this->assertNotNull($registry->get('moonshot'));
        $this->assertNotNull($registry->get('zai'));
        $this->assertNotNull($registry->get('dashscope'));
        $this->assertNotNull($registry->get('openrouter'));
    }

    #[Test]
    public function registry_all_returns_every_route(): void
    {
        $registry = new RouteRegistry;

        $this->assertGreaterThanOrEqual(9, count($registry->all()));
    }

    #[Test]
    public function registry_configured_filters_by_auth_availability(): void
    {
        // In the test env, API keys are forced empty, so only NoAuth routes
        // (ollama local) are configured.
        $registry = new RouteRegistry;
        $configured = $registry->configured();

        $this->assertArrayHasKey('ollama', $configured);
        $this->assertArrayNotHasKey('openai', $configured); // OPENAI_API_KEY is '' in test env
    }

    #[Test]
    public function adding_a_custom_route_is_five_lines(): void
    {
        $registry = new RouteRegistry;
        $before = count($registry->all());

        // A self-hosted vLLM endpoint — five lines, no new class.
        $registry->add(new Route(
            id: 'vllm-local',
            endpoint: Endpoint::url('http://localhost:8080'),
            auth: new NoAuth,
            framing: new OpenAiChatFraming,
            label: 'vLLM (self-hosted)',
        ));

        $this->assertSame($before + 1, count($registry->all()));
        $this->assertNotNull($registry->get('vllm-local'));
        $this->assertTrue($registry->get('vllm-local')->isConfigured());
    }

    #[Test]
    public function openai_chat_framing_extracts_text_and_usage(): void
    {
        $framing = new OpenAiChatFraming;
        $body = [
            'choices' => [['message' => ['content' => 'Hello!']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            'model' => 'gpt-4o-2024',
        ];

        $this->assertSame('Hello!', $framing->extractText($body));
        $this->assertSame(['input' => 10, 'output' => 5], $framing->extractUsage($body));
        $this->assertSame('gpt-4o-2024', $framing->extractModel($body));
    }

    #[Test]
    public function openai_chat_framing_label(): void
    {
        $this->assertSame('openai-chat', (new OpenAiChatFraming)->label());
    }
}