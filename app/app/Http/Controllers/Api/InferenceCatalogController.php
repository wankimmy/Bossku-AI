<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\ModelAutoSelectorService;
use App\Services\Llm\ProviderFactory;
use App\Services\OAuth\CodexOAuthService;
use Illuminate\Http\Request;

class InferenceCatalogController extends Controller
{
    public function __construct(
        protected RuntimeSettings $settings,
        protected CodexOAuthService $codexOAuth,
        protected ModelAutoSelectorService $autoSelector,
        protected ProviderFactory $providerFactory,
    ) {}

    public function index()
    {
        $providers = config('bossku_inference_catalog.providers', []);
        $groups = [];

        foreach ($providers as $slug => $meta) {
            $configured = $this->providerFactory->isProviderConfigured($slug);
            $auth = (string) ($meta['auth'] ?? 'api_key');

            $allModels = array_values(array_filter(
                config('bossku_inference_catalog.models', []),
                fn (array $m): bool => ($m['provider'] ?? '') === $slug && ($m['available'] ?? true) !== false,
            ));

            $groups[] = [
                'provider' => $slug,
                'name' => (string) ($meta['name'] ?? $slug),
                'auth' => $auth,
                'configured' => $configured,
                'disabled' => ! $configured,
                'hint' => $this->providerHint($slug, $auth, $configured),
                'all_cloud_models' => array_map(fn (array $m): array => [
                    'id' => $m['id'],
                    'label' => $m['label'] ?? $m['id'],
                ], $allModels),
                'recommended_models' => $configured
                    ? $this->autoSelector->recommendForRole('orchestrator', $slug, 5)
                    : [],
            ];
        }

        return response()->json([
            'version' => config('bossku_inference_catalog.version'),
            'cloud_only' => true,
            'providers' => $groups,
            'anthropic_configured' => $this->settings->anthropicConfigured(),
            'codex_connected' => $this->codexOAuth->isConnected(),
            'ollama' => $this->legacyOllamaGroup(),
            'anthropic' => $this->legacyAnthropicGroup(),
            'codex' => $this->legacyCodexGroup(),
        ]);
    }

    public function recommendations(Request $request)
    {
        $role = (string) $request->query('role', 'orchestrator');
        $provider = $request->query('provider');
        $limit = min(10, max(1, (int) $request->query('limit', 3)));

        if ($provider !== null && $provider !== '') {
            return response()->json([
                'role' => $role,
                'provider' => $provider,
                'recommended_models' => $this->autoSelector->recommendForRole($role, (string) $provider, $limit),
                'auto_selected' => $this->autoSelector->autoSelectModel($role, (string) $provider),
            ]);
        }

        return response()->json([
            'role' => $role,
            'providers' => $this->autoSelector->cloudProvidersForRole($role, $limit),
        ]);
    }

    public function applyRecommendations(Request $request)
    {
        $data = $request->validate([
            'roles' => 'sometimes|array',
            'roles.*' => 'string|max:100',
        ]);

        $roles = $data['roles'] ?? [
            'router', 'direct_answer', 'orchestrator', 'executor',
            'auditor', 'security_auditor', 'final_reviewer', 'writer',
        ];

        $applied = [];
        foreach ($roles as $role) {
            $providers = $this->autoSelector->cloudProvidersForRole($role, 1);
            foreach ($providers as $p) {
                if (! $p['configured'] || $p['recommended_models'] === []) {
                    continue;
                }
                $top = $p['recommended_models'][0];
                $applied[$role] = [
                    'provider' => $p['provider'],
                    'model' => $top['id'],
                    'score' => $top['score'],
                ];
                break;
            }
        }

        return response()->json(['applied' => $applied]);
    }

    public function applyModelRoutes(Request $request, \App\Services\BosskuAi\RuntimeSettings $settings)
    {
        $data = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.role' => 'required|string|max:100',
            'assignments.*.provider' => 'required|string|max:100',
            'assignments.*.model' => 'required|string|max:255',
        ]);

        $settingKeyMap = [
            'router' => 'router_model',
            'direct_answer' => 'direct_answer_model',
            'orchestrator' => 'orchestrator_model',
            'planner' => 'reasoning_model',
            'executor' => 'coding_model',
            'coder' => 'coding_model',
            'auditor' => 'auditor_model',
            'security_auditor' => 'security_auditor_model',
            'final_reviewer' => 'final_reviewer_model',
            'writer' => 'writer_model',
        ];

        $saved = [];
        foreach ($data['assignments'] as $assignment) {
            $key = $settingKeyMap[$assignment['role']] ?? null;
            if ($key !== null) {
                \App\Models\BosskuAi\Setting::setValue($key, $assignment['model']);
                $saved[$assignment['role']] = $assignment['model'];
            }
        }

        return response()->json(['saved' => $saved]);
    }

    protected function providerHint(string $slug, string $auth, bool $configured): ?string
    {
        if ($configured) {
            return null;
        }

        return match ($auth) {
            'oauth' => 'Connect with ChatGPT in Settings → Models to enable Codex.',
            default => "Add an API key for {$slug} in Settings → Providers.",
        };
    }

    /** @return list<array{id: string, label: string}> */
    protected function legacyOllamaGroup(): array
    {
        return array_map(
            fn (array $m): array => ['id' => $m['id'], 'label' => $m['label'] ?? $m['id']],
            array_values(array_filter(
                config('bossku_inference_catalog.models', []),
                fn (array $m): bool => ($m['provider'] ?? '') === 'ollama-cloud',
            )),
        );
    }

    /** @return list<array{id: string, label: string}> */
    protected function legacyAnthropicGroup(): array
    {
        if (! $this->settings->anthropicConfigured()) {
            return [];
        }

        return array_map(
            fn (array $m): array => ['id' => $m['id'], 'label' => $m['label'] ?? $m['id']],
            array_values(array_filter(
                config('bossku_inference_catalog.models', []),
                fn (array $m): bool => ($m['provider'] ?? '') === 'anthropic' && ($m['available'] ?? true) !== false,
            )),
        );
    }

    /** @return list<array{id: string, label: string}> */
    protected function legacyCodexGroup(): array
    {
        if (! $this->codexOAuth->isConnected()) {
            return [];
        }

        return array_map(
            fn (array $m): array => ['id' => $m['id'], 'label' => $m['label'] ?? $m['id']],
            array_values(array_filter(
                config('bossku_inference_catalog.models', []),
                fn (array $m): bool => ($m['provider'] ?? '') === 'codex',
            )),
        );
    }
}
