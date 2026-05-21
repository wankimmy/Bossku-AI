<?php

namespace App\Services\Llm;

use App\Models\BosskuAi\ModelRoute;
use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;

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
     * @return array{provider: LlmProviderInterface, model: string}
     */
    public function resolve(LlmRequest $request): array
    {
        // 1. Force-provider override
        if ($request->forceProvider !== null) {
            $provider = $this->providers[$request->forceProvider] ?? null;

            if ($provider === null) {
                throw new \RuntimeException(
                    "Forced provider '{$request->forceProvider}' is not registered."
                );
            }

            return ['provider' => $provider, 'model' => $request->model];
        }

        // 2. DB route lookup by role
        $route = ModelRoute::where('role', $request->role)
            ->where('is_active', true)
            ->first();

        if ($route !== null) {
            $dbProvider = $route->primaryProvider;

            if ($dbProvider !== null) {
                $provider = $this->providers[$dbProvider->slug] ?? null;

                if ($provider !== null) {
                    return ['provider' => $provider, 'model' => $route->primary_model];
                }
            }
        }

        // 3. Fallback to Ollama
        $ollama = $this->providers['ollama'] ?? null;

        if ($ollama !== null) {
            return ['provider' => $ollama, 'model' => $request->model];
        }

        throw new \RuntimeException(
            "No provider could be resolved for role '{$request->role}' and no Ollama fallback is registered."
        );
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
