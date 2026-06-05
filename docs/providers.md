# LLM Providers

BosskuAI supports multiple LLM providers through a unified provider abstraction. You can run entirely on local Ollama, entirely on a single cloud provider, or mix providers across agent roles.

## Settings → Models (primary path)

Use **Settings → Models** (`/settings/models`) to configure the credentials most teams need:

| Provider | How to connect | Model dropdown |
|---|---|---|
| **Ollama Cloud** | `OLLAMA_API_KEY` in `.env` or API key field on the page | Ollama Cloud optgroup (`*:cloud` tags) |
| **Anthropic Claude** | API key field (encrypted in DB) or `ANTHROPIC_API_KEY` in `.env` | Anthropic optgroup when a key is configured |
| **Codex (ChatGPT)** | **Connect with ChatGPT** — browser OAuth via OpenAI | Codex optgroup when connected |

The inference gateway picks the provider from the **model id** you select per role (for example `claude-sonnet-4-5` → Anthropic, `gpt-4o` → Codex, `kimi-k2.6:cloud` → Ollama). No separate per-role provider override is required.

### Anthropic API key

1. Create a key at [Anthropic Console](https://console.anthropic.com/settings/keys).
2. Paste it under **Anthropic** on Settings → Models and save (or set `ANTHROPIC_API_KEY` in `app/.env` for headless deploys).
3. Choose Claude models from the grouped dropdowns.

Keys are stored encrypted in `bossku_ai_settings.anthropic_api_key_encrypted` (same pattern as the Ollama key).

### Codex OAuth (ChatGPT login)

Codex uses OpenAI’s OAuth flow (PKCE), similar to the Codex CLI:

1. Set `CODEX_OAUTH_CLIENT_ID` and `CODEX_OAUTH_REDIRECT_URI` in `app/.env`. The default client id matches the public Codex CLI app; production installs should register their own OAuth app.
2. Register redirect URI `http://localhost:28480/api/oauth/codex/callback` (or your API host) with OpenAI.
3. On Settings → Models, click **Connect with ChatGPT**. After login, you are redirected back with `?codex=connected`.
4. Tokens are stored encrypted in `bossku_ai_settings.codex_auth_encrypted` and refreshed automatically before expiry.

API routes: `GET /api/oauth/codex/status`, `GET /api/oauth/codex/authorize`, `GET /api/oauth/codex/callback`, `DELETE /api/oauth/codex`.

Model list for the UI: `GET /api/settings/inference-catalog`.

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

Run manually:

```bash
php artisan bosskuai:provider-health
```

The command probes **registered runtime providers** (`ollama`, `anthropic`, `codex` from `AppServiceProvider`) using each provider's `healthCheck()` method. DB provider rows whose slug does not map to a registered provider (for example `ollama-cloud` without normalization) are marked **down** with an explanatory message.

**Not implemented yet:** automatic 5-minute cron, `provider_health_logs` table, or skipping unhealthy providers inside `ModelRouter::resolve()` (health status on the DB row is updated by the command but not consulted during routing).

## Model Syncing

**Sync Models** on `/settings/providers` returns `not_implemented: true` until automatic catalog sync is built. Use **Settings → Models** and `GET /api/settings/inference-catalog` for model pickers today.

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
