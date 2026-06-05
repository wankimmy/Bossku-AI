<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;
use App\Services\Llm\ModelRegistry;
use App\Services\OAuth\CodexOAuthService;
use Illuminate\Support\Facades\Http;

class CodexOAuthProvider implements LlmProviderInterface
{
    public function __construct(
        protected CodexOAuthService $codexOAuth,
        protected string $baseUrl = 'https://api.openai.com',
    ) {}

    public function getSlug(): string
    {
        return 'codex';
    }

    public function complete(LlmRequest $request): LlmResponse
    {
        $token = $this->codexOAuth->getAccessToken();

        $res = $this->http($token)->post(
            rtrim($this->baseUrl, '/').'/v1/chat/completions',
            [
                'model' => $request->model,
                'messages' => $request->messages,
                ...($request->maxTokens !== null ? ['max_tokens' => $request->maxTokens] : []),
                ...($request->temperature !== null ? ['temperature' => $request->temperature] : []),
            ],
        );

        $res->throw();

        $body = $res->json();
        $text = (string) data_get($body, 'choices.0.message.content', '');
        $inputTokens = (int) data_get($body, 'usage.prompt_tokens', 0);
        $outputTokens = (int) data_get($body, 'usage.completion_tokens', 0);
        $resolved = (string) data_get($body, 'model', $request->model);
        $costUsd = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new LlmResponse(
            text: $text,
            provider: 'codex',
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
        return array_column(config('bossku_oauth.codex_models', []), 'id');
    }

    public function healthCheck(): ProviderHealthStatus
    {
        if (! $this->codexOAuth->isConnected()) {
            return new ProviderHealthStatus(
                provider: 'codex',
                status: 'down',
                error: 'Not connected',
            );
        }

        return new ProviderHealthStatus(
            provider: 'codex',
            status: 'healthy',
            latencyMs: 0,
        );
    }

    public function estimateCost(LlmRequest $request): CostEstimate
    {
        $inputTokens = (int) (strlen(implode(' ', array_column($request->messages, 'content'))) / 4);
        $outputTokens = $request->maxTokens ?? 1024;
        $costUsd = ModelRegistry::estimateCost($request->model, $inputTokens, $outputTokens);

        return new CostEstimate(
            estimatedUsd: $costUsd,
            estimatedInputTokens: $inputTokens,
            estimatedOutputTokens: $outputTokens,
            provider: 'codex',
            model: $request->model,
        );
    }

    protected function http(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(120)
            ->acceptJson()
            ->withToken($token);
    }
}
