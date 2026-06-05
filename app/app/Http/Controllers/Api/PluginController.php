<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Plugin;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function index()
    {
        return response()->json(Plugin::orderBy('name')->get());
    }

    public function show(string $id)
    {
        return response()->json(Plugin::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:bossku_ai_plugins,slug',
            'description' => 'nullable|string',
            'version'     => 'nullable|string|max:50',
            'author'      => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'manifest'    => 'nullable|array',
            'is_active'   => 'boolean',
        ]);

        $plugin = Plugin::create($data);

        return response()->json($plugin, 201);
    }

    public function update(string $id, Request $request)
    {
        $plugin = Plugin::findOrFail($id);

        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|unique:bossku_ai_plugins,slug,'.$id,
            'description' => 'nullable|string',
            'version'     => 'nullable|string|max:50',
            'author'      => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'manifest'    => 'nullable|array',
            'is_active'   => 'boolean',
        ]);

        $plugin->update($data);

        return response()->json($plugin);
    }

    public function destroy(string $id)
    {
        Plugin::findOrFail($id)->delete();

        return response()->json(['message' => 'Plugin deleted.']);
    }

    public function heartbeat(string $id)
    {
        $plugin = Plugin::findOrFail($id);
        $plugin->update(['last_heartbeat_at' => now()]);

        return response()->json(['message' => 'Heartbeat recorded.', 'last_heartbeat_at' => $plugin->last_heartbeat_at]);
    }
}
