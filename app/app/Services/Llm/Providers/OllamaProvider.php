<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;
use App\Services\Llm\OllamaClient;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements LlmProviderInterface
{
    public function __construct(
        protected OllamaClient $ollama,
        protected string $baseUrl = 'http://127.0.0.1:11434',
    ) {}

    public function getSlug(): string
    {
        return 'ollama';
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $out = $this->ollama->chatWithUsage(
            $request->model,
            $request->messages,
            $request->temperature,
            $request->maxTokens,
            $request->responseFormat,
        );

        return new LlmResponse(
            text: $out['text'],
            provider: 'ollama',
            modelLogical: $request->model,
            modelResolved: $request->model,
            inputTokens: $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null,
            outputTokens: $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null,
            costUsd: 0.0,
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
            ->get(rtrim($this->baseUrl, '/').'/api/tags');

        if (! $res->successful()) {
            return [];
        }

        return array_column($res->json('models', []), 'name');
    }

    public function healthCheck(): ProviderHealthStatus
    {
        $start = hrtime(true);

        try {
            $res = Http::timeout(5)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/api/tags');

            $latency = (int) ((hrtime(true) - $start) / 1_000_000);

            if ($res->successful()) {
                return new ProviderHealthStatus(
                    provider: 'ollama',
                    status: 'healthy',
                    latencyMs: $latency,
                );
            }

            return new ProviderHealthStatus(
                provider: 'ollama',
                status: 'degraded',
                latencyMs: $latency,
                error: 'HTTP '.$res->status(),
            );
        } catch (\Throwable $e) {
            return new ProviderHealthStatus(
                provider: 'ollama',
                status: 'down',
                error: $e->getMessage(),
            );
        }
    }

    public function estimateCost(LlmRequest $request): CostEstimate
    {
        $inputTokens  = (int) (strlen(implode(' ', array_column($request->messages, 'content'))) / 4);
        $outputTokens = (int) (($request->maxTokens ?? 512));

        return new CostEstimate(
            estimatedUsd: 0.0,
            estimatedInputTokens: $inputTokens,
            estimatedOutputTokens: $outputTokens,
            provider: 'ollama',
            model: $request->model,
        );
    }
}
