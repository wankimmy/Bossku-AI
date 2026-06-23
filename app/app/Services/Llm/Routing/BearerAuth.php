<?php

namespace App\Services\Llm\Routing;

/**
 * Bearer-token auth: adds `Authorization: Bearer <token>` to the request.
 * The token is resolved lazily via a callable so env vars / secrets are read
 * only when the route is actually used, not at registration time.
 */
final class BearerAuth extends Auth
{
    /**
     * @param  callable(): ?string  $tokenResolver  returns the API key or null if not configured
     * @param  string  $headerName  the header to set (default: Authorization)
     */
    public function __construct(
        private readonly \Closure $tokenResolver,
        private readonly string $headerName = 'Authorization',
    ) {}

    public function apply(array $request): array
    {
        $token = ($this->tokenResolver)();
        if (is_string($token) && $token !== '') {
            $request['headers'][$this->headerName] = 'Bearer '.$token;
        }

        return $request;
    }

    public function label(): string
    {
        return 'bearer';
    }
}