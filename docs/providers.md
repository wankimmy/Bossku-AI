# LLM Providers

BosskuAI supports multiple LLM providers through a unified provider abstraction. You can run entirely on local Ollama, entirely on a single cloud provider, or mix providers across agent roles.

## Supported Provider Types

| Type | Description |
|---|---|
| `anthropic` | Anthropic Claude models (claude-sonnet-4-6, claude-opus-4, claude-haiku-3-5, etc.) |
| `openai` | OpenAI models (gpt-4o, gpt-4o-mini, o1, etc.) |
| `ollama` | Local or remote Ollama instances — any model pulled in Ollama |
| `openai_compatible` | Any provider with an OpenAI-compatible chat completions API (Together AI, Fireworks, Groq, vLLM, LM Studio, etc.) |
| `custom` | Bring-your-own adapter by implementing `LlmProviderInterface` |

## How API Keys Are Stored

API keys are stored encrypted in the `llm_providers` table using Laravel's `encrypted` cast. The encryption key is your `APP_KEY` value from `.env`. Keys are never returned in API responses — the provider list endpoint returns the key prefix (first 8 chars + `...`) for identification only.

To rotate a key: edit the provider in `/settings/providers`, paste the new key, save. The old key is immediately overwritten.

**Never commit API keys to your repo.** Use `.env` for local development and a secrets manager (AWS Secrets Manager, Doppler, etc.) in production.

## Adding a Provider

1. Navigate to `/settings/providers`
2. Click **Add Provider**
3. Select the provider type
4. Enter:
   - **Name** — a label for your own reference (e.g. "Anthropic Production", "Local Ollama")
   - **API Key** — required for all types except `ollama` (which uses no auth by default)
   - **Base URL** — required for `ollama` and `openai_compatible`; optional for `openai` and `anthropic` (used to override the default endpoint, e.g. for proxies)
5. Click **Save & Test** — the system immediately sends a lightweight health check request to verify the key and endpoint work

## Health Checks

`ProviderHealthService` runs a health check for each configured provider every 5 minutes. A health check sends a minimal chat completion request (single token response) and measures:

- **Latency** (ms)
- **HTTP status** (200 = healthy, anything else = degraded/down)
- **Error message** if the call failed

Health status is visible as a colored indicator on the `/settings/providers` page and in the model routing UI. `ModelRouter` skips providers with status `down` when resolving routes.

Health check results are stored in `provider_health_logs` for the last 24 hours, giving you a rough uptime history.

## Model Syncing

For Anthropic, OpenAI, and most `openai_compatible` providers, BosskuAI can sync the available model list:

1. On the provider detail page, click **Sync Models**
2. The system calls the provider's models endpoint and updates the `provider_models` table
3. The synced models appear in the model route selector dropdown

For Ollama, model sync calls `GET /api/tags` and lists all pulled models. Pull new models in Ollama separately (`ollama pull <model>`), then sync in BosskuAI.

For `custom` providers, the model list is entered manually.

## The `openai_compatible` Type

Use this for any provider that speaks the OpenAI chat completions API format. Required fields:

- **Base URL** — the provider's API endpoint (e.g. `https://api.groq.com/openai`)
- **API Key** — the provider's API key
- **Model name** — must be entered manually in model routes (no sync available unless the provider implements `/v1/models`)

Examples:
- **Groq**: base URL `https://api.groq.com/openai`, model `llama-3.3-70b-versatile`
- **Together AI**: base URL `https://api.together.xyz`, model `meta-llama/Llama-3-70b-chat-hf`
- **Local vLLM**: base URL `http://localhost:8080`, model name matching your vLLM config

## Custom Providers

To implement a custom provider:

1. Create a class implementing `App\Services\Llm\LlmProviderInterface`
2. Register it in `App\Providers\LlmServiceProvider` with a unique type string
3. The type string will appear in the provider type dropdown

`LlmProviderInterface` requires:
- `chat(array $messages, array $options): string` — synchronous chat completion
- `stream(array $messages, array $options): Generator` — streaming chat completion
- `embed(string $text): array` — embedding vector (for memory/skill matching)
- `healthCheck(): bool` — called by `ProviderHealthService`
