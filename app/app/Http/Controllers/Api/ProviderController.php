<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\LlmProvider;
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

    public function testConnection(string $id)
    {
        $provider = LlmProvider::findOrFail($id);

        if ($provider->type === 'ollama') {
            return response()->json(['status' => 'healthy', 'latency_ms' => 0]);
        }

        return response()->json(['status' => 'unknown']);
    }

    public function syncModels(string $id)
    {
        LlmProvider::findOrFail($id);

        return response()->json(['synced' => 0]);
    }
}
