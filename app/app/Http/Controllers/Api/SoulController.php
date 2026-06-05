<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\SoulVersion;
use Illuminate\Http\Request;

class SoulController extends Controller
{
    public function show()
    {
        $soul = SoulVersion::active();

        if (! $soul) {
            return response()->json(['message' => 'No active soul version found.'], 404);
        }

        return response()->json([
            'id'      => $soul->id,
            'version' => $soul->version,
            'content' => $soul->content,
            'active'  => $soul->active,
        ]);
    }

    public function history()
    {
        $versions = SoulVersion::query()
            ->orderByDesc('created_at')
            ->get(['id', 'version', 'active', 'change_summary', 'created_at']);

        return response()->json($versions);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'content'        => 'required|string',
            'change_summary' => 'nullable|string|max:1000',
        ]);

        /** @var \App\Services\Soul\SoulService $service */
        $service = app(\App\Services\Soul\SoulService::class);
        $newVersion = $service->createVersion($data['content'], $data['change_summary'] ?? null);

        return response()->json($newVersion);
    }

    public function suggestions()
    {
        return response()->json([]);
    }
}
