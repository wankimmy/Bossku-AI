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

    public function clarification(string $id, OrchestratorService $orchestrator)
    {
        return response()->json($orchestrator->clarificationForRun($id));
    }

    public function continueStream(Request $request, string $id, OrchestratorService $orchestrator): StreamedResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|string|max:64',
            'answers.*.option_id' => 'nullable|string|max:64',
            'answers.*.free_text' => 'nullable|string|max:20000',
        ]);

        /** @var list<array{question_id: string, option_id?: string|null, free_text?: string|null}> $answers */
        $answers = $validated['answers'];

        return response()->stream(function () use ($orchestrator, $id, $answers) {
            try {
                $orchestrator->continueRun($id, $answers, function (array $evt) {
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
            'Access-Control-Allow-Origin' => env('FRONTEND_URL', 'http://localhost:28470'),
            'Vary' => 'Origin',
        ]);
    }

    public function store(Request $request, OrchestratorService $orchestrator)
    {
        $data = $this->validateRunInput($request);

        try {
            $result = $orchestrator->run($data['prompt'], null, $data['conversation']);
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

        return $this->streamRun($orchestrator, $validated['prompt'], []);
    }

    public function streamPost(Request $request, OrchestratorService $orchestrator): StreamedResponse
    {
        $data = $this->validateRunInput($request);

        return $this->streamRun($orchestrator, $data['prompt'], $data['conversation']);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    private function streamRun(OrchestratorService $orchestrator, string $prompt, array $conversation): StreamedResponse
    {
        return response()->stream(function () use ($orchestrator, $prompt, $conversation) {
            try {
                $orchestrator->run($prompt, function (array $evt) {
                    echo 'data: '.json_encode($evt, JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }, $conversation);
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
            'Access-Control-Allow-Origin' => env('FRONTEND_URL', 'http://localhost:28470'),
            'Vary' => 'Origin',
        ]);
    }

    /**
     * @return array{prompt: string, conversation: list<array{role: string, content: string}>}
     */
    private function validateRunInput(Request $request): array
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:50000',
            'conversation' => 'sometimes|array|max:50',
            'conversation.*.role' => 'required_with:conversation|in:user,assistant',
            'conversation.*.content' => 'required_with:conversation|string|max:20000',
        ]);

        /** @var list<array{role: string, content: string}> $conversation */
        $conversation = $validated['conversation'] ?? [];

        return [
            'prompt' => $validated['prompt'],
            'conversation' => $conversation,
        ];
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
