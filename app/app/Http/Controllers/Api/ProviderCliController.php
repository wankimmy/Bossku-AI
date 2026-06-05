<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Services\Providers\CliSessionService;
use App\Services\Providers\ProviderCliRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderCliController extends Controller
{
    public function __construct(
        private readonly ProviderCliRegistry $registry,
        private readonly CliSessionService $sessions,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'providers' => $this->registry->all(),
            'installed' => $this->registry->detectInstalled(),
        ]);
    }

    public function start(Request $request, string $runId): JsonResponse
    {
        $run = Run::query()->find($runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $validated = $request->validate([
            'provider' => 'required|string|max:64',
            'prompt' => 'nullable|string|max:50000',
            'timeout' => 'nullable|integer|min:30|max:3600',
            'async' => 'nullable|boolean',
        ]);

        $prompt = (string) ($validated['prompt'] ?? $run->prompt);
        $session = $this->sessions->start($run, $validated['provider'], $prompt, [
            'timeout' => $validated['timeout'] ?? 300,
            'async' => $validated['async'] ?? null,
        ]);

        return response()->json($session, 201);
    }

    public function show(string $runId, string $sessionId): JsonResponse
    {
        $run = Run::query()->find($runId);
        if ($run === null) {
            return response()->json(['message' => 'Run not found.'], 404);
        }

        $session = $this->sessions->show($sessionId);
        if ($session === null || (string) $session->run_id !== (string) $run->getKey()) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        return response()->json($session);
    }
}
