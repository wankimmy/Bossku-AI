<?php

namespace App\Services\Llm;

use App\Services\Llm\Contracts\LlmProviderInterface;

class ProviderRegistry
{
    /** @var array<string, LlmProviderInterface>|null */
    protected ?array $cache = null;

    public function __construct(
        protected ProviderFactory $factory,
        protected ModelRouter $router,
    ) {}

    public function refresh(): void
    {
        $this->cache = null;
        $this->router->clearProviders();

        foreach ($this->factory->buildAllActive() as $slug => $provider) {
            $this->router->registerProvider($provider);
        }
    }

    /** @return array<string, LlmProviderInterface> */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->refresh();
            $this->cache = $this->router->registeredProviders();
        }

        return $this->cache;
    }

    public function get(string $slug): ?LlmProviderInterface
    {
        $normalized = $this->router->normalizeProviderSlug($slug);

        return $this->all()[$normalized] ?? null;
    }

    public function isConfigured(string $slug): bool
    {
        return $this->factory->isProviderConfigured($slug);
    }
}
