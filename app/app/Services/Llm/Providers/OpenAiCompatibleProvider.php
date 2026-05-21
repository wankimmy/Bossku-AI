<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;
use App\Services\Llm\ModelRegistry;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleProvider implements LlmProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.openai.com',
        protected string $slug = 'openai_compatible',
    ) {}

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $res = $this->http()->post(
            rtrim($this->baseUrl, '/').'/v1/chat/completions',
            [
                'model'    => $request->model,
                'messages' => $request->messages,
                ...($request->maxTokens !== null ? ['max_tokens' => $request->maxTokens] : []),
                ...($request->temperature !== null ? ['temperature' => $request->temperature] : []),
            ],
        );

        $res->throw();

        $body         = $res->json();
        $text         = (string) data_get($body, 'choices.0.message.content', '');
        $inputTokens  = (int) data_get($body, 'usage.prompt_tokens', 0);
        $outputTokens = (int) data_get($body, 'usage.completion_tokens', 0);
        $resolved     = (string) data_get($body, 'model', $request->model);
        $costUsd      = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new LlmResponse(
            text: $text,
            provider: $this->slug,
            modelLogical: $request->model,
            modelResolved: $resolved,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costUsd: $costUsd,
        );
    }

    public function stream(LlmRequest $request): iterable
    {
        $response = $this->complete($request);
        yield $response->text;
    }

    public function listModels(): array
    {
        $res = Http::timeout(10)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->get(rtrim($this->baseUrl, '/').'/v1/models');

        if (! $res->successful()) {
            return [];
        }

        return array_column($res->json('data', []), 'id');
    }

    public function healthCheck(): ProviderHealthStatus
    {
        $start = hrtime(true);

        try {
            $this->complete(new LlmRequest(
                model: 'gpt-4o-mini',
                messages: [['role' => 'user', 'content' => 'ping']],
                maxTokens: 4,
            ));

            $latency = (int) ((hrtime(true) - $start) / 1_000_000);

            return new ProviderHealthStatus(
                provider: $this->slug,
                status: 'healthy',
                latencyMs: $latency,
            );
        } catch (\Throwable $e) {
            return new ProviderHealthStatus(
                provider: $this->slug,
                status: 'down',
                error: $e->getMessage(),
            );
        }
    }

    public function estimateCost(LlmRequest $request): CostEstimate
    {
        $inputTokens  = (int) (strlen(implode(' ', array_column($request->messages, 'content'))) / 4);
        $outputTokens = $request->maxTokens ?? 1024;
        $costUsd      = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new CostEstimate(
            estimatedUsd: $costUsd,
            estimatedInputTokens: $inputTokens,
            estimatedOutputTokens: $outputTokens,
            provider: $this->slug,
            model: $request->model,
        );
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(120)
            ->acceptJson()
            ->withToken($this->apiKey);
    }
}
