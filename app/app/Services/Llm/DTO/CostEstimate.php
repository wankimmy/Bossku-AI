<?php

namespace App\Services\Llm\DTO;

class CostEstimate
{
    public function __construct(
        public readonly float $estimatedUsd,
        public readonly int $estimatedInputTokens,
        public readonly int $estimatedOutputTokens,
        public readonly string $provider,
        public readonly string $model,
    ) {}
}
