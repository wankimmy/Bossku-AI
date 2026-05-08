<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function index()
    {
        return Run::query()
            ->withCount('steps')
            ->orderByDesc('created_at')
            ->paginate(30);
    }

    public function show(string $id)
    {
        return Run::query()->with('steps')->findOrFail($id);
    }

    public function store(Request $request, OrchestratorService $orchestrator)
    {
        $data = $request->validate([
            'prompt' => 'required|string|max:50000',
        ]);

        try {
            $result = $orchestrator->run($data['prompt']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function stream(Request $request, OrchestratorService $orchestrator): StreamedResponse
    {
        $validated = validator([
            'prompt' => $request->query('prompt'),
        ], [
            'prompt' => 'required|string|max:50000',
        ])->validate();

        $prompt = $validated['prompt'];

        return response()->stream(function () use ($orchestrator, $prompt) {
            try {
                $orchestrator->run($prompt, function (array $evt) {
                    echo 'data: '.json_encode($evt, JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                });
            } catch (\Throwable $e) {
                echo 'data: '.json_encode([
                    'type' => 'run_failed',
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                ], JSON_THROW_ON_ERROR)."\n\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => env('FRONTEND_URL', 'http://localhost:3000'),
            'Vary' => 'Origin',
        ]);
    }
}
