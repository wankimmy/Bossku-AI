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

        $payload = $this->buildPayload($request);

        $res = $this->http()->post(
            rtrim($this->baseUrl, '/').'/v1/messages',
            $payload,
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

    /** @return array<string, mixed> */
    protected function buildPayload(LlmRequest $request): array
    {
        $system = null;
        $messages = [];

        foreach ($request->messages as $msg) {
            $role = (string) ($msg['role'] ?? 'user');
            $content = (string) ($msg['content'] ?? '');

            if ($role === 'system') {
                $system = $system === null ? $content : $system."\n\n".$content;

                continue;
            }

            if ($role === 'assistant' || $role === 'user') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $payload = [
            'model' => $request->model,
            'messages' => $messages,
            'max_tokens' => $request->maxTokens ?? 4096,
            ...($request->temperature !== null ? ['temperature' => $request->temperature] : []),
        ];

        if ($system !== null && $system !== '') {
            // Use extended-thinking block format so Anthropic can cache the static system prompt.
            // cache_control type "ephemeral" is billed at 10% of input tokens after a cache hit —
            // large system prompts (1k+ tokens) save significant cost on repeated calls.
            $payload['system'] = [
                ['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']],
            ];
        }

        return $payload;
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(120)
            ->acceptJson()
            ->withHeaders([
                'x-api-key'             => $this->apiKey,
                'anthropic-version'     => '2023-06-01',
                'anthropic-beta'        => 'prompt-caching-2024-07-31',
            ]);
    }
}
