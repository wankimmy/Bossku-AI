<?php

namespace App\Services\Llm\Routing;

/**
 * The endpoint axis: where the request goes. Ported from opencode's Endpoint.
 * Holds the base URL and the path suffix (e.g. /v1/chat/completions). The
 * full URL is base + path. Resolved lazily via a callable so env substitution
 * happens at use time.
 */
final class Endpoint
{
    /**
     * @param  callable(): string  $baseUrlResolver
     * @param  string  $path  e.g. '/v1/chat/completions'
     */
    public function __construct(
        private readonly \Closure $baseUrlResolver,
        public readonly string $path = '/v1/chat/completions',
    ) {}

    public function baseUrl(): string
    {
        return ($this->baseUrlResolver)();
    }

    public function fullUrl(): string
    {
        return rtrim($this->baseUrl(), '/').$this->path;
    }

    /** Convenience for a static base URL. */
    public static function url(string $baseUrl, string $path = '/v1/chat/completions'): self
    {
        return new self(fn () => $baseUrl, $path);
    }
}