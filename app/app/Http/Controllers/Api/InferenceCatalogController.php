<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\OAuth\CodexOAuthService;

class InferenceCatalogController extends Controller
{
    public function __construct(
        protected RuntimeSettings $settings,
        protected CodexOAuthService $codexOAuth,
    ) {}

    public function index()
    {
        $anthropic = [];
        if ($this->settings->anthropicConfigured()) {
            $anthropic = config('bossku_oauth.anthropic_models', []);
        }

        $codex = [];
        if ($this->codexOAuth->isConnected()) {
            $codex = config('bossku_oauth.codex_models', []);
        }

        return response()->json([
            'ollama' => $this->catalogModels('ollama_cloud_models'),
            'anthropic' => $anthropic,
            'codex' => $codex,
            'anthropic_configured' => $this->settings->anthropicConfigured(),
            'codex_connected' => $this->codexOAuth->isConnected(),
        ]);
    }

    /** @return list<array{id: string, label: string}> */
    protected function catalogModels(string $key): array
    {
        $models = config("bossku_oauth.{$key}");

        if (is_array($models) && $models !== []) {
            return $models;
        }

        $file = config_path('bossku_oauth.php');
        if (is_readable($file)) {
            /** @var array<string, mixed> $all */
            $all = require $file;
            $fallback = $all[$key] ?? [];

            return is_array($fallback) ? $fallback : [];
        }

        return [];
    }
}
