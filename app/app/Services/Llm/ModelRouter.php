<?php

namespace App\Services\Llm;

use App\Models\BosskuAi\ModelRoute;
use App\Models\BosskuAi\UsageEvent;
use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use Illuminate\Support\Carbon;

class ModelRouter
{
    /** @var array<string, LlmProviderInterface> */
    protected array $providers = [];

    public function __construct(
        protected UsageTracker $usageTracker,
    ) {}

    public function registerProvider(LlmProviderInterface $provider): void
    {
        $this->providers[$provider->getSlug()] = $provider;
    }

    /**
     * @return array<string, LlmProviderInterface>
     */
    public function registeredProviders(): array
    {
        return $this->providers;
    }

    public function normalizeProviderSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return match ($slug) {
            'ollama-local', 'ollama-cloud' => 'ollama',
            default => $slug,
        };
    }

    /**
     * @return array{provider: LlmProviderInterface, model: string}
     */
    public function resolve(LlmRequest $request): array
    {
        if ($request->forceProvider !== null) {
            $normalized = $this->normalizeProviderSlug($request->forceProvider);
            $provider = $this->providers[$normalized] ?? null;

            if ($provider === null) {
                throw new \RuntimeException(
                    "Forced provider '{$request->forceProvider}' is not registered."
                );
            }

            return ['provider' => $provider, 'model' => $request->model];
        }

        /** @var list<array{slug: string, model: string}> $candidates */
        $candidates = [];

        $route = ModelRoute::query()
            ->where('role', $request->role)
            ->where('is_active', true)
            ->with(['primaryProvider', 'fallbackProvider'])
            ->first();

        if ($route !== null && ! $this->isRouteOverBudget($route)) {
            if ($route->primaryProvider !== null && $route->primary_model !== '') {
                $candidates[] = [
                    'slug' => (string) $route->primaryProvider->slug,
                    'model' => (string) $route->primary_model,
                ];
            }
            if ($route->fallbackProvider !== null && $route->fallback_model !== '') {
                $candidates[] = [
                    'slug' => (string) $route->fallbackProvider->slug,
                    'model' => (string) $route->fallback_model,
                ];
            }
        }

        $candidates[] = ['slug' => 'ollama', 'model' => $request->model];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeProviderSlug($candidate['slug']);
            $provider = $this->providers[$normalized] ?? null;

            if ($provider !== null) {
                return ['provider' => $provider, 'model' => $candidate['model']];
            }
        }

        throw new \RuntimeException(
            "No provider could be resolved for role '{$request->role}' and no Ollama fallback is registered."
        );
    }

    protected function isRouteOverBudget(ModelRoute $route): bool
    {
        $budget = $route->monthly_budget_usd;
        if ($budget === null || (float) $budget <= 0) {
            return false;
        }

        $start = Carbon::now()->startOfMonth();
        $spent = (float) UsageEvent::query()
            ->where('role', $route->role)
            ->where('created_at', '>=', $start)
            ->sum('cost_usd');

        return $spent >= (float) $budget;
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        ['provider' => $provider, 'model' => $model] = $this->resolve($request);

        $resolvedRequest = new LlmRequest(
            model: $model,
            messages: $request->messages,
            role: $request->role,
            temperature: $request->temperature,
            maxTokens: $request->maxTokens,
            forceProvider: $request->forceProvider,
            runId: $request->runId,
            runStepId: $request->runStepId,
            metadata: $request->metadata,
        );

        $response = $provider->complete($resolvedRequest);

        $this->usageTracker->track($request, $response);

        return $response;
    }
}
