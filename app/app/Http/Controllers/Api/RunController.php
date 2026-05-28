<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\RunStreamEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function __construct(
        private readonly RunStreamEventService $streamEventLog,
    ) {}

    private function findRun(string $id): ?Run
    {
        return Run::query()->find($id);
    }

    private function runNotFoundResponse(string $id): JsonResponse
    {
        return response()->json([
            'message' => 'Run not found. It may have been removed or never created—start a new task and try again.',
            'run_id' => $id,
        ], 404);
    }

    public function index()
    {
        return Run::query()
            ->withCount('steps')
            ->orderByDesc('created_at')
            ->paginate(30);
    }

    public function show(string $id): JsonResponse
    {
        $run = $this->findRun($id);
        if ($run === null) {
            return $this->runNotFoundResponse($id);
        }

        $run->load('steps');

        return response()->json($run);
    }

    public function streamEvents(Request $request, string $id): JsonResponse
    {
        $run = $this->findRun($id);
        if ($run === null) {
            return $this->runNotFoundResponse($id);
        }

        $afterSeq = max(0, (int) $request->query('after_seq', 0));

        return response()->json($this->streamEventLog->eventsSince($run, $afterSeq));
    }

    public function clarification(string $id, OrchestratorService $orchestrator)
    {
        return response()->json($orchestrator->clarificationForRun($id));
    }

    public function approvals(string $id, OrchestratorService $orchestrator)
    {
        return response()->json($orchestrator->approvalsForRun($id));
    }

    public function continueApprovalsStream(string $id, OrchestratorService $orchestrator): StreamedResponse
    {
        return response()->stream(function () use ($orchestrator, $id) {
            $this->streamEventLog->beginBackgroundStream();

            try {
                $orchestrator->continueAfterApprovals($id, $this->streamEventLog->sseEmitter());
            } catch (\Throwable $e) {
                $failed = [
                    'type' => 'run_failed',
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                    'run_id' => $id,
                ];
                ($this->streamEventLog->sseEmitter())($failed);
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

    public function continueStream(Request $request, string $id, OrchestratorService $orchestrator): StreamedResponse|JsonResponse
    {
        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.question_id' => 'required|string|max:64',
            'answers.*.option_id' => 'nullable|string|max:64',
            'answers.*.free_text' => 'nullable|string|max:20000',
            'review_decision' => 'nullable|string|in:approve,request_changes',
            'code_review_comment' => 'nullable|string|max:20000',
        ]);

        /** @var list<array{question_id: string, option_id?: string|null, free_text?: string|null}> $answers */
        $answers = $validated['answers'];
        $reviewDecision = (string) ($validated['review_decision'] ?? 'approve');
        $codeReviewComment = isset($validated['code_review_comment'])
            ? (string) $validated['code_review_comment']
            : null;

        if ($reviewDecision === 'request_changes' && trim((string) $codeReviewComment) === '') {
            return response()->json([
                'message' => 'code_review_comment is required when review_decision is request_changes.',
            ], 422);
        }

        $misroute = $this->executorApprovalsMisrouteResponse($id);
        if ($misroute !== null) {
            return $misroute;
        }

        return response()->stream(function () use ($orchestrator, $id, $answers, $reviewDecision, $codeReviewComment) {
            $this->streamEventLog->beginBackgroundStream();

            try {
                $orchestrator->continueRun(
                    $id,
                    $answers,
                    $this->streamEventLog->sseEmitter(),
                    $reviewDecision,
                    $codeReviewComment,
                );
            } catch (\Throwable $e) {
                $failed = [
                    'type' => 'run_failed',
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                    'run_id' => $id,
                ];
                ($this->streamEventLog->sseEmitter())($failed);
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
        $started = microtime(true);

        Log::info('bossku.run.started', [
            'channel' => 'sync',
            'ip' => $request->ip(),
            'prompt_length' => strlen($data['prompt']),
        ]);

        try {
            $result = $orchestrator->run($data['prompt'], null, $data['conversation']);
        } catch (\Throwable $e) {
            Log::warning('bossku.run.failed', [
                'ip' => $request->ip(),
                'prompt_length' => strlen($data['prompt']),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        Log::info('bossku.run.completed', [
            'run_id' => $result['run_id'] ?? null,
            'ip' => $request->ip(),
            'prompt_length' => strlen($data['prompt']),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'status' => $result['routing']['risk_level'] ?? null,
        ]);

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
        $ip = request()->ip();
        $promptLength = strlen($prompt);

        return response()->stream(function () use ($orchestrator, $prompt, $conversation, $ip, $promptLength) {
            $this->streamEventLog->beginBackgroundStream();

            $started = microtime(true);
            $runId = null;
            $emit = $this->streamEventLog->sseEmitter(function (array $evt) use (&$runId): void {
                if (isset($evt['run_id'])) {
                    $runId = $evt['run_id'];
                }
            });

            Log::info('bossku.run.stream_started', [
                'ip' => $ip,
                'prompt_length' => $promptLength,
            ]);

            try {
                $orchestrator->run($prompt, $emit, $conversation);

                Log::info('bossku.run.stream_completed', [
                    'run_id' => $runId,
                    'ip' => $ip,
                    'prompt_length' => $promptLength,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                ]);
            } catch (\Throwable $e) {
                Log::warning('bossku.run.stream_failed', [
                    'run_id' => $runId,
                    'ip' => $ip,
                    'prompt_length' => $promptLength,
                    'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                    'error' => $e->getMessage(),
                ]);

                $emit([
                    'type' => 'run_failed',
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                    'run_id' => $runId,
                ]);
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

    private function executorApprovalsMisrouteResponse(string $runId): ?JsonResponse
    {
        $run = Run::query()->find($runId);
        if ($run === null || $run->status !== 'awaiting_input') {
            return null;
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
        if (($checkpoint['stage'] ?? null) !== 'executor_approvals') {
            return null;
        }

        return response()->json([
            'message' => 'Run is awaiting change approvals. POST /api/runs/'.$runId.'/continue-approvals/stream after all items are approved or rejected.',
            'stage' => 'executor_approvals',
            'resume_endpoint' => '/api/runs/'.$runId.'/continue-approvals/stream',
        ], 409);
    }
}
