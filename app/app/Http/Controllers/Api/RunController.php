<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\FeedbackItem;
use App\Models\BosskuAi\Run;
use App\Services\Attachments\AttachmentRunContextService;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Runs\LongPromptTempFileService;
use App\Services\RunStreamEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunController extends Controller
{
    public function __construct(
        private readonly RunStreamEventService $streamEventLog,
        private readonly LongPromptTempFileService $longPromptFiles,
        private readonly AttachmentRunContextService $attachmentContext,
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

        $run->load(['steps', 'workspace', 'childRuns.workspace', 'cliSessions', 'parentRun', 'reactionStates']);

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
        // A missing run here is expected (e.g. the browser is polling a run id left over
        // from a previous DB state). Return a clean 404 instead of letting the orchestrator
        // throw a RuntimeException, which Laravel renders as a 500 and logs as an ERROR on
        // every poll.
        if ($this->findRun($id) === null) {
            return $this->runNotFoundResponse($id);
        }

        return response()->json($orchestrator->clarificationForRun($id));
    }

    public function approvals(string $id, OrchestratorService $orchestrator)
    {
        if ($this->findRun($id) === null) {
            return $this->runNotFoundResponse($id);
        }

        return response()->json($orchestrator->approvalsForRun($id));
    }

    public function continueApprovalsStream(string $id, OrchestratorService $orchestrator): StreamedResponse
    {
        return response()->stream(function () use ($orchestrator, $id) {
            $this->streamEventLog->beginBackgroundStream();
            $terminal = false;
            $emit = $this->streamEventLog->sseEmitter(function (array $evt) use (&$terminal): void {
                if (in_array((string) ($evt['type'] ?? ''), ['run_completed', 'run_failed'], true)) {
                    $terminal = true;
                }
            });

            try {
                $orchestrator->continueAfterApprovals($id, $emit);
            } catch (\Throwable $e) {
                $failed = [
                    'type' => 'run_failed',
                    'status' => 'fail',
                    'error' => $e->getMessage(),
                    'run_id' => $id,
                ];
                $terminal = true;
                $emit($failed);
            } finally {
                $this->cleanupLongPromptIfTerminal($id, $emit, $terminal);
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
            $terminal = false;
            $emit = $this->streamEventLog->sseEmitter(function (array $evt) use (&$terminal): void {
                if (in_array((string) ($evt['type'] ?? ''), ['run_completed', 'run_failed'], true)) {
                    $terminal = true;
                }
            });

            try {
                $orchestrator->continueRun(
                    $id,
                    $answers,
                    $emit,
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
                $terminal = true;
                $emit($failed);
            } finally {
                $this->cleanupLongPromptIfTerminal($id, $emit, $terminal);
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
        $prepared = $this->preparePromptForRun(
            $this->promptWithAttachments($data['prompt'], $data['attachment_ids']),
        );
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }
        $started = microtime(true);

        Log::info('bossku.run.started', [
            'channel' => 'sync',
            'ip' => $request->ip(),
            'prompt_length' => strlen($data['prompt']),
            'attachment_count' => count($data['attachment_ids']),
            'long_prompt_materialized' => $prepared['materialized'],
        ]);

        try {
            $result = $this->runPreparedPrompt($orchestrator, $prepared, null, $data['conversation']);

            $runId = (string) ($result['run_id'] ?? '');
            $this->linkAttachmentsToRun($data['attachment_ids'], $runId);
            $this->finalizePreparedPrompt($prepared, $runId, null);
        } catch (\Throwable $e) {
            $this->finalizePreparedPrompt($prepared, null, null, true);

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

        return $this->streamRun($orchestrator, $this->longPromptFiles->inline($validated['prompt']), []);
    }

    public function streamPost(Request $request, OrchestratorService $orchestrator): StreamedResponse|JsonResponse
    {
        $data = $this->validateRunInput($request);
        $misroute = $this->awaitingClarificationMisrouteResponse($data['conversation']);
        if ($misroute !== null) {
            return $misroute;
        }
        $prepared = $this->preparePromptForRun(
            $this->promptWithAttachments($data['prompt'], $data['attachment_ids']),
        );
        if ($prepared instanceof JsonResponse) {
            return $prepared;
        }

        return $this->streamRun($orchestrator, $prepared, $data['conversation'], $data['attachment_ids']);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  list<string>  $attachmentIds
     * @param  array{prompt: string, routing_prompt: string, materialized: bool, metadata: array<string, mixed>|null}  $prepared
     */
    private function streamRun(
        OrchestratorService $orchestrator,
        array $prepared,
        array $conversation,
        array $attachmentIds = [],
    ): StreamedResponse {
        $ip = request()->ip();
        $promptLength = strlen($prepared['prompt']);
        $originalPromptLength = is_array($prepared['metadata'] ?? null)
            ? (int) ($prepared['metadata']['original_length'] ?? $promptLength)
            : $promptLength;

        return response()->stream(function () use ($orchestrator, $prepared, $conversation, $attachmentIds, $ip, $promptLength, $originalPromptLength) {
            $this->streamEventLog->beginBackgroundStream();

            $started = microtime(true);
            $runId = null;
            $terminal = false;
            $emit = $this->streamEventLog->sseEmitter(function (array $evt) use (&$runId): void {
                if (isset($evt['run_id'])) {
                    $runId = $evt['run_id'];
                }
            });
            $emitTracked = function (array $evt) use ($emit, &$runId, &$terminal): void {
                if (isset($evt['run_id'])) {
                    $runId = (string) $evt['run_id'];
                }
                if (in_array((string) ($evt['type'] ?? ''), ['run_completed', 'run_failed'], true)) {
                    $terminal = true;
                }
                $emit($evt);
            };

            if ($prepared['materialized'] && is_array($prepared['metadata'])) {
                $emitTracked($this->longPromptFiles->materializedEvent($prepared['metadata']));
            }

            Log::info('bossku.run.stream_started', [
                'ip' => $ip,
                'prompt_length' => $promptLength,
                'original_prompt_length' => $originalPromptLength,
                'long_prompt_materialized' => $prepared['materialized'],
            ]);

            try {
                $result = $this->runPreparedPrompt($orchestrator, $prepared, $emitTracked, $conversation);
                if ($runId === null && isset($result['run_id'])) {
                    $runId = (string) $result['run_id'];
                }
                if ($runId !== null && $runId !== '') {
                    $this->linkAttachmentsToRun($attachmentIds, $runId);
                }

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
                $terminal = true;
            } finally {
                $this->finalizePreparedPrompt($prepared, $runId, $emitTracked, $terminal);
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
     * @return array{
     *   prompt: string,
     *   conversation: list<array{role: string, content: string}>,
     *   attachment_ids: list<string>
     * }
     */
    private function validateRunInput(Request $request): array
    {
        $maxAttachments = max(1, (int) config('bossku.attachments.max_per_run', 10));

        $validated = $request->validate([
            'prompt' => 'required|string|max:'.LongPromptTempFileService::MAX_ACCEPTED_CHARS,
            'conversation' => 'sometimes|array|max:50',
            'conversation.*.role' => 'required_with:conversation|in:user,assistant',
            'conversation.*.content' => 'required_with:conversation|string|max:20000',
            'attachment_ids' => 'sometimes|array|max:'.$maxAttachments,
            'attachment_ids.*' => 'uuid',
        ]);

        /** @var list<array{role: string, content: string}> $conversation */
        $conversation = $validated['conversation'] ?? [];
        /** @var list<string> $attachmentIds */
        $attachmentIds = array_values(array_unique($validated['attachment_ids'] ?? []));

        return [
            'prompt' => $validated['prompt'],
            'conversation' => $conversation,
            'attachment_ids' => $attachmentIds,
        ];
    }

    /**
     * @param  list<string>  $attachmentIds
     */
    private function promptWithAttachments(string $prompt, array $attachmentIds): string
    {
        return $this->attachmentContext->prependToPrompt($prompt, $attachmentIds);
    }

    /**
     * @param  list<string>  $attachmentIds
     */
    private function linkAttachmentsToRun(array $attachmentIds, string $runId): void
    {
        if ($runId === '' || $attachmentIds === []) {
            return;
        }

        $this->attachmentContext->linkToRun($attachmentIds, $runId);
    }

    /**
     * @return array{prompt: string, routing_prompt: string, materialized: bool, metadata: array<string, mixed>|null}|JsonResponse
     */
    private function preparePromptForRun(string $prompt): array|JsonResponse
    {
        try {
            return $this->longPromptFiles->prepare($prompt);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param  array{prompt: string, routing_prompt: string, materialized: bool, metadata: array<string, mixed>|null}  $prepared
     * @return array<string, mixed>
     */
    private function orchestratorOptionsForPreparedPrompt(array $prepared): array
    {
        if (! $prepared['materialized'] || ! is_array($prepared['metadata'])) {
            return [];
        }

        return [
            'routing_prompt' => $prepared['routing_prompt'],
            'long_prompt_attachment' => true,
            'metadata' => [
                'long_prompt' => $prepared['metadata'],
            ],
        ];
    }

    /**
     * @param  array{prompt: string, routing_prompt: string, materialized: bool, metadata: array<string, mixed>|null}  $prepared
     * @param  callable(array<string, mixed>): void|null  $emit
     * @param  list<array{role: string, content: string}>  $conversation
     * @return array<string, mixed>
     */
    private function runPreparedPrompt(
        OrchestratorService $orchestrator,
        array $prepared,
        ?callable $emit,
        array $conversation,
    ): array {
        $options = $this->orchestratorOptionsForPreparedPrompt($prepared);
        if ($options === []) {
            return $orchestrator->run($prepared['prompt'], $emit, $conversation);
        }

        return $orchestrator->run($prepared['prompt'], $emit, $conversation, $options);
    }

    /**
     * @param  array{prompt: string, routing_prompt: string, materialized: bool, metadata: array<string, mixed>|null}  $prepared
     * @param  callable(array<string, mixed>): void|null  $emit
     */
    private function finalizePreparedPrompt(array $prepared, ?string $runId, ?callable $emit = null, bool $force = false): void
    {
        if (! $prepared['materialized'] || ! is_array($prepared['metadata'])) {
            return;
        }

        $run = ($runId !== null && $runId !== '') ? Run::query()->find($runId) : null;
        $terminal = $force || $run === null || in_array((string) $run->status, ['completed', 'failed'], true);
        if (! $terminal) {
            $this->longPromptFiles->storeRunMetadata($runId, array_merge($prepared['metadata'], [
                'cleanup_status' => 'kept_for_resume',
            ]));
            return;
        }

        $cleaned = $this->longPromptFiles->cleanupRun($runId, $prepared['metadata']);
        if ($emit !== null && is_array($cleaned)) {
            $emit($this->longPromptFiles->cleanedEvent($runId, $cleaned));
        }
    }

    /** @param callable(array<string, mixed>): void $emit */
    private function cleanupLongPromptIfTerminal(string $runId, callable $emit, bool $terminal): void
    {
        $run = Run::query()->find($runId);
        $shouldClean = $terminal || in_array((string) ($run?->status ?? ''), ['completed', 'failed'], true);
        if (! $shouldClean) {
            return;
        }

        $cleaned = $this->longPromptFiles->cleanupRun($runId);
        if (is_array($cleaned)) {
            $emit($this->longPromptFiles->cleanedEvent($runId, $cleaned));
        }
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

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    private function awaitingClarificationMisrouteResponse(array $conversation): ?JsonResponse
    {
        if ($conversation === []) {
            return null;
        }

        $encoded = json_encode($conversation);
        if ($encoded === false) {
            return null;
        }

        $candidates = Run::query()
            ->where('status', 'awaiting_input')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        foreach ($candidates as $run) {
            /** @var array<string, mixed> $meta */
            $meta = is_array($run->metadata) ? $run->metadata : [];
            /** @var array<string, mixed> $checkpoint */
            $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
            if (($checkpoint['stage'] ?? null) === 'executor_approvals') {
                continue;
            }

            $storedConversation = is_array($meta['conversation'] ?? null) ? $meta['conversation'] : [];
            if (json_encode($storedConversation) !== $encoded) {
                continue;
            }

            return response()->json([
                'message' => 'A run is awaiting your input. Reply via continue/stream instead of starting a new run.',
                'awaiting_run_id' => $run->id,
                'stage' => $checkpoint['stage'] ?? null,
                'resume_endpoint' => '/api/runs/'.$run->id.'/continue/stream',
            ], 409);
        }

        return null;
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
