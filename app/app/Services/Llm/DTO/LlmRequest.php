<?php

namespace App\Services\Llm\DTO;

class LlmRequest
{
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly string $role = 'coder',
        public readonly ?float $temperature = 0.2,
        public readonly ?int $maxTokens = null,
        public readonly ?string $forceProvider = null,
        public readonly ?string $runId = null,
        public readonly ?string $runStepId = null,
        public readonly array $metadata = [],
        /** Response format hint for providers that support constrained decoding (e.g. Ollama "json"). */
        public readonly ?string $responseFormat = null,
    ) {}

    public static function make(string $model, array $messages, array $options = []): self
    {
        return new self(
            model: $model,
            messages: $messages,
            role: $options['role'] ?? 'coder',
            temperature: $options['temperature'] ?? 0.2,
            maxTokens: $options['max_tokens'] ?? null,
            forceProvider: $options['force_provider'] ?? null,
            runId: $options['run_id'] ?? null,
            runStepId: $options['run_step_id'] ?? null,
            metadata: $options['metadata'] ?? [],
            responseFormat: $options['response_format'] ?? null,
        );
    }
}
