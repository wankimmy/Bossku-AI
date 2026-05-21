<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    // -------------------------------------------------------------------------
    // Sub-resource methods
    // -------------------------------------------------------------------------

    public function timeline(string $id)
    {
        $run = Run::findOrFail($id);

        $steps = $run->steps()
            ->orderBy('step_number')
            ->get(['id', 'step_number', 'type', 'status', 'latency_ms', 'token_estimate', 'cost', 'created_at', 'updated_at']);

        return response()->json($steps);
    }

    public function messages(string $id)
    {
        $run = Run::findOrFail($id);

        return response()->json($run->agentMessages()->orderBy('created_at')->get());
    }

    public function toolCalls(string $id)
    {
        $run = Run::findOrFail($id);

        return response()->json($run->toolCalls()->orderBy('created_at')->get());
    }

    public function fileChanges(string $id)
    {
        $run = Run::findOrFail($id);

        return response()->json($run->fileChanges()->orderBy('created_at')->get());
    }

    public function auditData(string $id)
    {
        $run = Run::findOrFail($id);

        $steps = $run->steps()
            ->whereIn('type', ['auditor', 'final'])
            ->orderBy('step_number')
            ->get();

        return response()->json($steps);
    }

    public function usageData(string $id)
    {
        $run = Run::findOrFail($id);

        $events = $run->usageEvents()->orderBy('created_at')->get();

        $totals = [
            'total_input_tokens'  => (int) $events->sum('input_tokens'),
            'total_output_tokens' => (int) $events->sum('output_tokens'),
            'total_cost_usd'      => round((float) $events->sum('cost_usd'), 8),
        ];

        return response()->json([
            'events' => $events,
            'totals' => $totals,
        ]);
    }

    public function feedbackData(string $id)
    {
        Run::findOrFail($id);

        $feedback = FeedbackItem::query()
            ->where('target_type', 'run')
            ->where('target_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($feedback);
    }
}
