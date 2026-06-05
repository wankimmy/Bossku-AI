<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunScmController extends Controller
{
    public function show(string $runId): JsonResponse
    {
        $run = Run::query()->with('reactionStates')->find($runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $meta = is_array($run->metadata) ? $run->metadata : [];

        return response()->json([
            'run_id' => $run->getKey(),
            'scm' => $meta['scm'] ?? null,
            'reaction_states' => $run->reactionStates,
        ]);
    }

    public function attach(Request $request, string $runId): JsonResponse
    {
        $run = Run::query()->find($runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $validated = $request->validate([
            'provider' => 'nullable|string|max:32',
            'owner' => 'required|string|max:128',
            'repo' => 'required|string|max:128',
            'pull_number' => 'required|integer|min:1',
        ]);

        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['scm'] = [
            'provider' => (string) ($validated['provider'] ?? 'github'),
            'owner' => $validated['owner'],
            'repo' => $validated['repo'],
            'pull_number' => (int) $validated['pull_number'],
            'attached_at' => now()->toIso8601String(),
        ];

        $run->update(['metadata' => $meta]);

        return response()->json([
            'ok' => true,
            'run_id' => $run->getKey(),
            'scm' => $meta['scm'],
        ]);
    }
}
