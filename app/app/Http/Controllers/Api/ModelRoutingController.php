<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\ModelRoute;
use Illuminate\Http\Request;

class ModelRoutingController extends Controller
{
    public function index()
    {
        $routes = ModelRoute::with(['primaryProvider:id,name', 'fallbackProvider:id,name'])
            ->orderBy('role')
            ->get()
            ->map(fn (ModelRoute $r) => $this->routePayload($r));

        return response()->json($routes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'role'                  => 'required|string|max:100',
            'primary_provider_id'   => 'required|uuid|exists:bossku_ai_llm_providers,id',
            'primary_model'         => 'required|string|max:255',
            'fallback_provider_id'  => 'nullable|uuid|exists:bossku_ai_llm_providers,id',
            'fallback_model'        => 'nullable|string|max:255',
            'routing_rules_json'    => 'nullable|array',
            'monthly_budget_usd'    => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
        ]);

        $route = ModelRoute::create($data)->load(['primaryProvider:id,name', 'fallbackProvider:id,name']);

        return response()->json($this->routePayload($route), 201);
    }

    public function update(string $id, Request $request)
    {
        $route = ModelRoute::findOrFail($id);

        $data = $request->validate([
            'role'                  => 'sometimes|string|max:100',
            'primary_provider_id'   => 'sometimes|uuid|exists:bossku_ai_llm_providers,id',
            'primary_model'         => 'sometimes|string|max:255',
            'fallback_provider_id'  => 'nullable|uuid|exists:bossku_ai_llm_providers,id',
            'fallback_model'        => 'nullable|string|max:255',
            'routing_rules_json'    => 'nullable|array',
            'monthly_budget_usd'    => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
        ]);

        $route->update($data);
        $route->load(['primaryProvider:id,name', 'fallbackProvider:id,name']);

        return response()->json($this->routePayload($route));
    }

    public function destroy(string $id)
    {
        ModelRoute::findOrFail($id)->delete();

        return response()->json(['message' => 'Route deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function routePayload(ModelRoute $route): array
    {
        return array_merge($route->toArray(), [
            'primary_provider_name' => $route->primaryProvider?->name,
            'fallback_provider_name' => $route->fallbackProvider?->name,
        ]);
    }
}
