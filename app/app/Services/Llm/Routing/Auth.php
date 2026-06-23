<?php

namespace App\Services\Llm\Routing;

/**
 * The authentication strategy for an LLM route. Ported from opencode's Auth
 * axis. Decouples signing (bearer token, OAuth, Bedrock SigV4, no-auth) from
 * the protocol and endpoint, so a new provider can reuse an existing protocol
 * with a different auth method.
 *
 * @template-covariant A of Auth
 */
abstract class Auth
{
    /**
     * Apply the authentication to an HTTP request builder. Subclasses add
     * headers, query params, or sign the request as needed.
     *
     * @param  array{headers: array<string, string>, query: array<string, string>}  $request
     * @return array{headers: array<string, string>, query: array<string, string>}
     */
    abstract public function apply(array $request): array;

    /** Human-readable label for the route registry / UI. */
    abstract public function label(): string;
}