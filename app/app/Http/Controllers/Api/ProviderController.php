<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\LlmProvider;
use App\Services\Llm\ModelRouter;
use App\Services\Llm\OllamaClient;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index()
    {
        // api_key_encrypted is in $hidden on the model, so it won't be serialised
        return response()->json(LlmProvider::orderBy('name')->get());
    }

    public function show(string $id)
    {
        return response()->json(LlmProvider::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:bossku_ai_llm_providers,slug',
            'type'        => 'required|string|max:100',
            'base_url'    => 'nullable|url|max:500',
            'api_key'     => 'nullable|string',
            'api_key_env' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $apiKey = $data['api_key'] ?? null;
        unset($data['api_key']);

        $provider = LlmProvider::create($data);

        if ($apiKey) {
            $provider->setApiKey($apiKey);
            $provider->save();
        }

        return response()->json($provider, 201);
    }

    public function update(string $id, Request $request)
    {
        $provider = LlmProvider::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|unique:bossku_ai_llm_providers,slug,'.$id,
            'type'        => 'sometimes|string|max:100',
            'base_url'    => 'nullable|url|max:500',
            'api_key'     => 'nullable|string',
            'api_key_env' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $apiKey = $data['api_key'] ?? null;
        unset($data['api_key']);

        $provider->fill($data);

        if ($apiKey) {
            $provider->setApiKey($apiKey);
        }

        $provider->save();

        return response()->json($provider);
    }

    public function destroy(string $id)
    {
        LlmProvider::findOrFail($id)->delete();

        return response()->json(['message' => 'Provider deleted.']);
    }

    public function testConnection(string $id, ModelRouter $router, OllamaClient $ollama)
    {
        $provider = LlmProvider::findOrFail($id);
        $normalized = $router->normalizeProviderSlug((string) $provider->slug);
        $instance = $router->registeredProviders()[$normalized] ?? null;

        if ($instance === null) {
            return response()->json([
                'status' => 'unavailable',
                'message' => "Provider slug '{$provider->slug}' is not registered at runtime. Registered slugs: "
                    .implode(', ', array_keys($router->registeredProviders())),
            ], 422);
        }

        if ($normalized === 'ollama') {
            try {
                $out = $ollama->chatWithUsage('kimi-k2.6:cloud', [
                    ['role' => 'user', 'content' => 'Reply with exactly: ok'],
                ], 0.0);

                return response()->json([
                    'status' => 'healthy',
                    'latency_ms' => 0,
                    'preview' => mb_substr(trim($out['text']), 0, 80),
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'down',
                    'message' => $e->getMessage(),
                ], 503);
            }
        }

        $health = $instance->healthCheck();

        return response()->json([
            'status' => $health->status,
            'latency_ms' => $health->latencyMs,
            'error' => $health->error,
        ]);
    }

    public function syncModels(string $id)
    {
        LlmProvider::findOrFail($id);

        return response()->json([
            'synced' => 0,
            'not_implemented' => true,
            'message' => 'Automatic model sync is not implemented yet. Configure models in Settings → Models.',
        ]);
    }
}
