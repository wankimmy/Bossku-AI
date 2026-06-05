<?php

namespace App\Services\Llm\DTO;

class LlmResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $provider,
        public readonly string $modelLogical,
        public readonly string $modelResolved,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly float $costUsd = 0.0,
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'provider' => $this->provider,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'model_logical' => $this->modelLogical,
            'model_resolved' => $this->modelResolved,
            'cost_usd' => $this->costUsd,
        ];
    }
}
