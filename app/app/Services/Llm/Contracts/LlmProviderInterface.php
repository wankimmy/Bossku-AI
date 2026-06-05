<?php

namespace App\Services\Llm\Contracts;

use App\Services\Llm\DTO\CostEstimate;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\DTO\ProviderHealthStatus;

interface LlmProviderInterface
{
    public function complete(LlmRequest $request): LlmResponse;

    public function stream(LlmRequest $request): iterable;

    public function listModels(): array;

    public function healthCheck(): ProviderHealthStatus;

    public function estimateCost(LlmRequest $request): CostEstimate;

    public function getSlug(): string;
}
