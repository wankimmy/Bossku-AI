<?php

namespace App\Services\Llm\DTO;

class ProviderHealthStatus
{
    public function __construct(
        public readonly string $provider,
        public readonly string $status, // healthy|degraded|down
        public readonly ?int $latencyMs = null,
        public readonly ?string $error = null,
        public readonly array $details = [],
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }
}
