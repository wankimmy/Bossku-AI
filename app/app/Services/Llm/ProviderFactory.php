<?php

namespace App\Services\Llm;

use App\Models\BosskuAi\LlmProvider;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\Contracts\LlmProviderInterface;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\CodexOAuthProvider;
use App\Services\Llm\Providers\CustomProvider;
use App\Services\Llm\Providers\OllamaProvider;
use App\Services\Llm\Providers\OpenAiCompatibleProvider;
use App\Services\OAuth\CodexOAuthService;

class ProviderFactory
{
    /** @var array<string, array{base_url: string, api_key_env?: string}> */
    protected static array $presets = [
        'ollama-cloud' => ['base_url' => 'https://ollama.com', 'api_key_env' => 'OLLAMA_API_KEY'],
        'ollama' => ['base_url' => 'https://ollama.com', 'api_key_env' => 'OLLAMA_API_KEY'],
        'anthropic' => ['base_url' => 'https://api.anthropic.com', 'api_key_env' => 'ANTHROPIC_API_KEY'],
        'openai' => ['base_url' => 'https://api.openai.com', 'api_key_env' => 'OPENAI_API_KEY'],
        'deepseek' => ['base_url' => 'https://api.deepseek.com', 'api_key_env' => 'DEEPSEEK_API_KEY'],
        'moonshot' => ['base_url' => 'https://api.moonshot.ai/v1', 'api_key_env' => 'MOONSHOT_API_KEY'],
        'zai' => ['base_url' => 'https://api.z.ai/api/paas/v4', 'api_key_env' => 'ZHIPU_API_KEY'],
        'dashscope' => ['base_url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1', 'api_key_env' => 'DASHSCOPE_API_KEY'],
        'openrouter' => ['base_url' => 'https://openrouter.ai/api/v1', 'api_key_env' => 'OPENROUTER_API_KEY'],
    ];

    public function __construct(
        protected OllamaClient $ollamaClient,
        protected RuntimeSettings $settings,
        protected CodexOAuthService $codexOAuth,
    ) {}

    public function buildFromRecord(LlmProvider $record): ?LlmProviderInterface
    {
        if (! $record->is_active) {
            return null;
        }

        $slug = strtolower(trim($record->slug));
        $type = strtolower(trim($record->type));

        return match ($type) {
            'ollama' => new OllamaProvider(
                $this->ollamaClient,
                $record->base_url ?? self::$presets['ollama']['base_url'],
            ),
            'anthropic' => $this->buildAnthropic($record),
            'codex_oauth' => $this->buildCodex(),
            'openai', 'openai_compatible' => $this->buildOpenAiCompatible($record, $slug),
            'custom' => $this->buildCustom($record),
            default => $this->buildOpenAiCompatible($record, $slug),
        };
    }

    /** @return array<string, LlmProviderInterface> */
    public function buildAllActive(): array
    {
        $providers = [];

        $providers['ollama'] = new OllamaProvider($this->ollamaClient, $this->settings->ollamaBaseUrl());

        $anthropicKey = $this->settings->anthropicApiKey();
        if (is_string($anthropicKey) && $anthropicKey !== '') {
            $providers['anthropic'] = new AnthropicProvider($anthropicKey);
        }

        if ($this->codexOAuth->isConnected()) {
            $providers['codex'] = new CodexOAuthProvider(
                $this->codexOAuth,
                (string) config('bossku_oauth.codex.api_base_url', 'https://api.openai.com'),
            );
        }

        foreach (LlmProvider::where('is_active', true)->get() as $record) {
            $slug = strtolower(trim($record->slug));
            if (isset($providers[$slug])) {
                continue;
            }

            $instance = $this->buildFromRecord($record);
            if ($instance !== null) {
                $providers[$slug] = $instance;
            }
        }

        return $providers;
    }

    protected function buildAnthropic(LlmProvider $record): ?AnthropicProvider
    {
        $key = $record->resolveApiKey() ?? $this->settings->anthropicApiKey();
        if (! is_string($key) || $key === '') {
            return null;
        }

        return new AnthropicProvider($key, $record->base_url ?? 'https://api.anthropic.com');
    }

    protected function buildCodex(): ?CodexOAuthProvider
    {
        if (! $this->codexOAuth->isConnected()) {
            return null;
        }

        return new CodexOAuthProvider(
            $this->codexOAuth,
            (string) config('bossku_oauth.codex.api_base_url', 'https://api.openai.com'),
        );
    }

    protected function buildOpenAiCompatible(LlmProvider $record, string $slug): ?OpenAiCompatibleProvider
    {
        $key = $record->resolveApiKey();
        if (! is_string($key) || $key === '') {
            $preset = self::$presets[$slug] ?? null;
            if ($preset !== null && isset($preset['api_key_env'])) {
                $key = env($preset['api_key_env']);
            }
        }
        if (! is_string($key) || $key === '') {
            return null;
        }

        $baseUrl = $record->base_url
            ?? (self::$presets[$slug]['base_url'] ?? 'https://api.openai.com');

        return new OpenAiCompatibleProvider($key, $baseUrl, $slug);
    }

    protected function buildCustom(LlmProvider $record): ?CustomProvider
    {
        $key = $record->resolveApiKey();
        if (! is_string($key) || $key === '' || ! $record->base_url) {
            return null;
        }

        return new CustomProvider($key, $record->base_url);
    }

    public function isProviderConfigured(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        return match ($slug) {
            'ollama', 'ollama-cloud' => $this->settings->ollamaApiKey() !== null || $this->settings->ollamaBaseUrl() !== '',
            'anthropic' => $this->settings->anthropicConfigured(),
            'codex' => $this->codexOAuth->isConnected(),
            default => LlmProvider::where('slug', $slug)->where('is_active', true)->exists()
                || (isset(self::$presets[$slug]['api_key_env']) && env(self::$presets[$slug]['api_key_env']) !== null),
        };
    }
}
