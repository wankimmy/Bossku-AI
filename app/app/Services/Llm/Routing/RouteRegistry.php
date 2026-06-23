<?php

namespace App\Services\Llm\Routing;

/**
 * The registry of LLM routes. Adding a provider is a 5-line entry here, not a
 * new class. Ported from opencode's route registry model.
 *
 * The registry holds named Route objects; callers resolve by id. This is the
 * structured successor to ProviderFactory's hardcoded $presets array + match
 * switch. The existing ProviderFactory remains the legacy path; this registry
 * is the declarative successor that will eventually replace it.
 *
 * Built-in routes mirror the existing $presets so the two systems agree on the
 * provider set until the cutover.
 */
final class RouteRegistry
{
    /** @var array<string, Route> */
    private array $routes = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    public function add(Route $route): self
    {
        $this->routes[$route->id] = $route;

        return $this;
    }

    public function get(string $id): ?Route
    {
        return $this->routes[$id] ?? null;
    }

    /** @return array<string, Route> */
    public function all(): array
    {
        return $this->routes;
    }

    public function configured(): array
    {
        return array_filter($this->routes, fn (Route $r) => $r->isConfigured());
    }

    /**
     * Register the built-in routes. Each is a 5-line entry reusing the shared
     * OpenAiChatFraming — the only thing that differs is the endpoint and auth.
     * This is the opencode payoff: DeepSeek, Moonshot, ZAI, DashScope,
     * OpenRouter are all one-liners on top of the shared framing.
     */
    private function registerBuiltins(): void
    {
        $chat = new OpenAiChatFraming;

        $this->add(new Route(
            id: 'ollama',
            endpoint: Endpoint::url('http://localhost:11434', '/v1/chat/completions'),
            auth: new NoAuth,
            framing: $chat,
            label: 'Ollama (local)',
        ));

        $this->add(new Route(
            id: 'ollama-cloud',
            endpoint: Endpoint::url('https://ollama.com'),
            auth: new BearerAuth(fn () => env('OLLAMA_API_KEY')),
            framing: $chat,
            label: 'Ollama Cloud',
        ));

        $this->add(new Route(
            id: 'anthropic',
            endpoint: Endpoint::url('https://api.anthropic.com', '/v1/messages'),
            auth: new BearerAuth(fn () => env('ANTHROPIC_API_KEY'), 'x-api-key'),
            framing: $chat, // Note: Anthropic uses a different framing; this is a placeholder until AnthropicFraming is added.
            label: 'Anthropic Claude',
        ));

        $this->add(new Route(
            id: 'openai',
            endpoint: Endpoint::url('https://api.openai.com'),
            auth: new BearerAuth(fn () => env('OPENAI_API_KEY')),
            framing: $chat,
            label: 'OpenAI',
        ));

        $this->add(new Route(
            id: 'deepseek',
            endpoint: Endpoint::url('https://api.deepseek.com'),
            auth: new BearerAuth(fn () => env('DEEPSEEK_API_KEY')),
            framing: $chat,
            label: 'DeepSeek',
        ));

        $this->add(new Route(
            id: 'moonshot',
            endpoint: Endpoint::url('https://api.moonshot.ai/v1'),
            auth: new BearerAuth(fn () => env('MOONSHOT_API_KEY')),
            framing: $chat,
            label: 'Moonshot (Kimi)',
        ));

        $this->add(new Route(
            id: 'zai',
            endpoint: Endpoint::url('https://api.z.ai/api/paas/v4'),
            auth: new BearerAuth(fn () => env('ZHIPU_API_KEY')),
            framing: $chat,
            label: 'ZAI (Zhipu)',
        ));

        $this->add(new Route(
            id: 'dashscope',
            endpoint: Endpoint::url('https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
            auth: new BearerAuth(fn () => env('DASHSCOPE_API_KEY')),
            framing: $chat,
            label: 'Alibaba DashScope',
        ));

        $this->add(new Route(
            id: 'openrouter',
            endpoint: Endpoint::url('https://openrouter.ai/api/v1'),
            auth: new BearerAuth(fn () => env('OPENROUTER_API_KEY')),
            framing: $chat,
            label: 'OpenRouter',
        ));
    }
}