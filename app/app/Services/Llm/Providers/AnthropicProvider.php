<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;
use App\Services\Llm\ModelRegistry;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LlmProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function getSlug(): string
    {
        return 'anthropic';
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $start = hrtime(true);

        $res = $this->http()->post(
            rtrim($this->baseUrl, '/').'/v1/messages',
            [
                'model'      => $request->model,
                'messages'   => $request->messages,
                'max_tokens' => $request->maxTokens ?? 4096,
                ...($request->temperature !== null ? ['temperature' => $request->temperature] : []),
            ],
        );

        $res->throw();

        $body         = $res->json();
        $text         = (string) data_get($body, 'content.0.text', '');
        $inputTokens  = (int) data_get($body, 'usage.input_tokens', 0);
        $outputTokens = (int) data_get($body, 'usage.output_tokens', 0);
        $costUsd      = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new LlmResponse(
            text: $text,
            provider: 'anthropic',
            modelLogical: $request->model,
            modelResolved: (string) data_get($body, 'model', $request->model),
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
        return [
            'claude-opus-4-5',
            'claude-sonnet-4-5',
            'claude-haiku-4-5',
            'claude-opus-4',
            'claude-sonnet-4',
            'claude-haiku-3',
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
        ];
    }

    public function healthCheck(): ProviderHealthStatus
    {
        $start = hrtime(true);

        try {
            $this->complete(new LlmRequest(
                model: 'claude-haiku-4-5',
                messages: [['role' => 'user', 'content' => 'ping']],
                maxTokens: 4,
            ));

            $latency = (int) ((hrtime(true) - $start) / 1_000_000);

            return new ProviderHealthStatus(
                provider: 'anthropic',
                status: 'healthy',
                latencyMs: $latency,
            );
        } catch (\Throwable $e) {
            return new ProviderHealthStatus(
                provider: 'anthropic',
                status: 'down',
                error: $e->getMessage(),
            );
        }
    }

    public function estimateCost(LlmRequest $request): CostEstimate
    {
        $inputTokens  = (int) (strlen(implode(' ', array_column($request->messages, 'content'))) / 4);
        $outputTokens = $request->maxTokens ?? 4096;
        $costUsd      = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new CostEstimate(
            estimatedUsd: $costUsd,
            estimatedInputTokens: $inputTokens,
            estimatedOutputTokens: $outputTokens,
            provider: 'anthropic',
            model: $request->model,
        );
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ]);
    }
}
