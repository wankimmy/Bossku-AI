<?php

namespace App\Services\Llm\Routing;

/**
 * No-auth: for local providers (Ollama default) or providers that use other
 * mechanisms (e.g. OAuth via a separate flow). apply() is a no-op.
 */
final class NoAuth extends Auth
{
    public function apply(array $request): array
    {
        return $request;
    }

    public function label(): string
    {
        return 'none';
    }
}