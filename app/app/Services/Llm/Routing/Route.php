<?php

namespace App\Services\Llm\Routing;

/**
 * A single LLM route: the four-axis decomposition of a provider connection.
 * Ported from opencode's Route.make({ id, provider, protocol, endpoint, auth,
 * framing }). Adding a new provider is constructing one of these — no new
 * class required.
 *
 * @example
 *   $route = new Route(
 *       id: 'deepseek',
 *       endpoint: Endpoint::url('https://api.deepseek.com'),
 *       auth: new BearerAuth(fn () => env('DEEPSEEK_API_KEY')),
 *       framing: new OpenAiChatFraming,
 *   );
 */
final class Route
{
    public function __construct(
        public readonly string $id,
        public readonly Endpoint $endpoint,
        public readonly Auth $auth,
        public readonly Framing $framing,
        /** Display name for the UI / logs. */
        public readonly string $label = '',
    ) {}

    /**
     * Execute a completion request through this route. Builds the HTTP body
     * via framing, applies auth, POSTs to the endpoint, and parses the
     * response. Returns the text, usage, and resolved model.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{max_tokens?: int, temperature?: float}  $params
     * @return array{text: string, input_tokens: int, output_tokens: int, model: string}
     */
    public function complete(string $model, array $messages, array $params = []): array
    {
        $body = $this->framing->requestBody($model, $messages, $params);
        $request = $this->auth->apply([
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'query' => [],
        ]);

        $response = \Illuminate\Support\Facades\Http::withHeaders($request['headers'])
            ->withQueryParameters($request['query'])
            ->post($this->endpoint->fullUrl(), $body);

        $response->throw();
        $json = $response->json() ?? [];

        $usage = $this->framing->extractUsage($json);

        return [
            'text' => $this->framing->extractText($json),
            'input_tokens' => $usage['input'],
            'output_tokens' => $usage['output'],
            'model' => $this->framing->extractModel($json) ?: $model,
        ];
    }

    /**
     * Whether the route is configured (auth resolves to a non-empty token
     * when auth is required). A NoAuth route is always configured.
     */
    public function isConfigured(): bool
    {
        if ($this->auth instanceof NoAuth) {
            return true;
        }

        // For BearerAuth, check if the token resolves.
        if ($this->auth instanceof BearerAuth) {
            // Use a peek: apply and check if the header was set.
            $applied = $this->auth->apply(['headers' => [], 'query' => []]);

            return isset($applied['headers']['Authorization']);
        }

        return true;
    }
}