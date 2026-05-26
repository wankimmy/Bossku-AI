<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;
use App\Support\StringCoercion;

trait OrchestratorApprovalTrait
{
    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function applyOrPauseForExecutorApprovals(
        Run $run,
        array $execResult,
        array $pipeline,
        ?callable $emit,
    ): array {
        if (! $this->executorApprovals->requireUserApproval()) {
            $execResult = $this->applyExecutorFileChanges($run, $execResult, $emit);

            return $this->applyExecutorCommands($run, $execResult, $emit);
        }

        $fileProposal = $this->executorApprovals->proposeFileChanges($run->id, $execResult);
        $execResult = $fileProposal['execResult'];
        $pending = $fileProposal['pending_approval_ids'];

        if ($this->executorApprovals->requireUserApprovalForCommands()) {
            $commandPending = $this->executorApprovals->proposeCommands(
                $run->id,
                is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : [],
            );
            $pending = array_values(array_merge($pending, $commandPending));
        } else {
            $execResult = $this->applyExecutorCommands($run, $execResult, $emit);
        }

        if ($pending === []) {
            return $execResult;
        }

        $pipeline['exec_result'] = $execResult;

        return $this->pauseForExecutorApprovals($run, $pending, $pipeline, $emit);
    }

    public function approvalsForRun(string $runId): array
    {
        $run = Run::query()->findOrFail($runId);
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'stage' => $checkpoint['stage'] ?? null,
            'pending' => $this->executorApprovals->pendingPayloadForRun($runId),
        ];
    }

    /**
     * Resume pipeline after all executor approvals are decided.
     *
     * @return array<string, mixed>
     */
    public function continueAfterApprovals(string $runId, ?callable $emit = null): array
    {
        $run = Run::query()->findOrFail($runId);
        if ($run->status !== 'awaiting_input') {
            throw new \InvalidArgumentException('Run is not awaiting approval decisions (status: '.$run->status.').');
        }

        if ($this->executorApprovals->hasPendingForRun($runId)) {
            $pendingCount = count($this->executorApprovals->pendingIdsForRun($runId));
            throw new \InvalidArgumentException(
                'Run still has pending approvals ('.$pendingCount.' remaining).',
            );
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
        $stage = (string) ($checkpoint['stage'] ?? '');
        if ($stage !== 'executor_approvals') {
            throw new \InvalidArgumentException('Run checkpoint is not awaiting executor approvals.');
        }

        /** @var array<string, mixed> $pipeline */
        $pipeline = is_array($checkpoint['pipeline'] ?? null) ? $checkpoint['pipeline'] : [];
        unset($meta['checkpoint']);
        $run->update(['status' => 'running', 'metadata' => $meta]);

        $feedback = $this->executorApprovals->formatDecisionFeedback($runId);
        if ($feedback !== '') {
            $this->emit($emit, $this->events->approvalFeedbackReceived($run, $feedback));
        }

        foreach ($this->executorApprovals->rejectedFileWritesForRun($runId) as $rejectedApproval) {
            $this->executorApprovals->revertRejectedFileWrite($rejectedApproval);
        }

        return $this->resumeFromExecutorApprovals($run, $pipeline, $feedback, $emit);
    }

    /**
     * @param  list<string>  $pendingIds
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function pauseForExecutorApprovals(
        Run $run,
        array $pendingIds,
        array $pipeline,
        ?callable $emit,
    ): array {
        $first = Approval::query()->find($pendingIds[0] ?? '');
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['checkpoint'] = [
            'phase' => 'awaiting_approvals',
            'stage' => 'executor_approvals',
            'approval_ids' => $pendingIds,
            'pipeline' => $pipeline,
        ];

        $run->update([
            'status' => 'awaiting_input',
            'metadata' => $meta,
        ]);

        $pendingCount = count($pendingIds);
        $summary = $pendingCount.' change(s) need your approval before the run can continue.';

        $this->emit($emit, $this->events->approvalRequested(
            $run,
            $pendingIds,
            $first !== null ? $this->executorApprovals->serializeApproval($first) : null,
            $pendingCount,
        ));
        $this->emit($emit, $this->events->runPaused($run, 'executor_approvals', $summary));

        return [
            'run_id' => $run->id,
            'status' => 'awaiting_input',
            'awaiting_approvals' => true,
            'stage' => 'executor_approvals',
            'pending_count' => count($pendingIds),
            'current_approval' => $first !== null
                ? $this->executorApprovals->serializeApproval($first)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function resumeFromExecutorApprovals(
        Run $run,
        array $pipeline,
        string $feedback,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $execResult */
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        $execResult = $this->mergeApprovalOutcomesIntoExecResult($run->id, $execResult);

        if ($feedback !== '') {
            $agentPrompt = trim((string) ($pipeline['agent_prompt'] ?? '')."\n\n".$feedback);
            $pipeline['agent_prompt'] = $agentPrompt;
            $execResult['user_approval_feedback'] = $feedback;
        }

        $reviewOutcome = $this->runExecutorRevisionForUserCodeReview($run, $execResult, $pipeline, $feedback, $emit);
        $execResult = $reviewOutcome['execResult'];
        $pipeline = $reviewOutcome['pipeline'];
        if ($reviewOutcome['paused'] !== null) {
            return $reviewOutcome['paused'];
        }

        if ($this->executorApprovals->hasRejectedFileWritesWithoutReviewNotes($run->id)) {
            $revertOutcome = $this->runExecutorRevertForRejectedFiles($run, $execResult, $pipeline, $feedback, $emit);
            $execResult = $revertOutcome['execResult'];
            $pipeline = $revertOutcome['pipeline'];
            if ($revertOutcome['paused'] !== null) {
                return $revertOutcome['paused'];
            }
        }

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
        $agentPrompt = (string) ($pipeline['agent_prompt'] ?? '');
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = is_array($pipeline['model_route'] ?? null) ? $pipeline['model_route'] : [];
        /** @var array<string, string> $modelsResolved */
        $modelsResolved = is_array($pipeline['models_resolved'] ?? null) ? $pipeline['models_resolved'] : [];
        /** @var array<string, mixed> $routerCtx */
        $routerCtx = is_array($pipeline['router_ctx'] ?? null) ? $pipeline['router_ctx'] : [];
        /** @var array<string, mixed> $plan */
        $plan = is_array($pipeline['plan'] ?? null) ? $pipeline['plan'] : [];
        /** @var list<array<string, mixed>> $memPayload */
        $memPayload = is_array($pipeline['mem_payload'] ?? null) ? $pipeline['mem_payload'] : [];
        $workflow = (string) ($pipeline['workflow'] ?? $modelRoute['workflow'] ?? '');
        $tokenAcc = (int) ($pipeline['token_acc'] ?? 0);
        $tRun = (float) ($pipeline['t_run'] ?? microtime(true));
        $preflightReads = is_array($pipeline['preflight_reads'] ?? null) ? $pipeline['preflight_reads'] : [];
        $execProfileKey = (string) ($pipeline['exec_profile_key'] ?? 'default');
        $skillName = (string) ($pipeline['skill_name'] ?? 'cofounder');
        /** @var array<string, mixed> $step */
        $step = is_array($pipeline['step'] ?? null) ? $pipeline['step'] : [];
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];
        $executorOutputs = is_array($pipeline['executor_outputs'] ?? null) ? $pipeline['executor_outputs'] : [$execResult];
        $stepNum = (int) ($pipeline['step_num'] ?? 3);

        if (! $this->executorApprovals->requireUserApproval()) {
            $execResult = $this->applyExecutorCommands($run, $execResult, $emit);
        } else {
            $execResult = $this->recordApprovedCommandResults($run, $execResult, $emit);
        }

        $this->projectCommands->logDockerAvailability();

        $exTok = $this->estimateTokens(json_encode($execResult) ?: '');
        $tokenAcc += $exTok;
        $this->emit($emit, $this->events->executorDone($run, $execResult, $modelsResolved['executor'] ?? '', (int) ($execResult['latency_ms'] ?? 0), $exTok));

        return $this->runPostExecutorPhase(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $memPayload,
            $routerCtx,
            $plan,
            $workflow,
            $execResult,
            $step,
            $skillRow,
            $skillName,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            $preflightReads,
            $execProfileKey,
            $emit,
            $tokenAcc,
            $tRun,
            $executorOutputs,
            $stepNum,
        );
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function mergeApprovalOutcomesIntoExecResult(string $runId, array $execResult): array
    {
        $byId = Approval::query()
            ->where('run_id', $runId)
            ->whereIn('status', ['approved', 'rejected', 'auto_approved'])
            ->get()
            ->keyBy('id');

        $files = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
        foreach ($files as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $aid = (string) ($item['approval_id'] ?? '');
            if ($aid === '' || ! $byId->has($aid)) {
                continue;
            }
            $approval = $byId->get($aid);
            $files[$idx]['approval_status'] = $approval->status;
            if ($approval->decision_note) {
                $files[$idx]['user_note'] = $approval->decision_note;
            }
        }
        $execResult['files_changed'] = $files;

        return $execResult;
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function recordApprovedCommandResults(Run $run, array $execResult, ?callable $emit): array
    {
        $approved = Approval::query()
            ->where('run_id', $run->id)
            ->where('operation_type', 'terminal_command')
            ->whereIn('status', ['approved', 'auto_approved'])
            ->orderBy('created_at')
            ->get();

        $executed = [];
        foreach ($approved as $approval) {
            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $cmd = (string) ($evidence['command'] ?? '');
            if ($cmd === '') {
                continue;
            }
            $result = is_array($evidence['command_result'] ?? null) ? $evidence['command_result'] : [];
            $executed[] = [
                'command' => $cmd,
                'exit_code' => (int) ($result['exit_code'] ?? 0),
                'stdout' => StringCoercion::toString($result['stdout'] ?? null, ''),
                'stderr' => StringCoercion::toString($result['stderr'] ?? null, ''),
                'ok' => ($result['ok'] ?? true) === true,
                'applied_via_approval' => true,
            ];
        }

        $rejected = Approval::query()
            ->where('run_id', $run->id)
            ->where('operation_type', 'terminal_command')
            ->where('status', 'rejected')
            ->pluck('operation_description')
            ->all();

        if ($rejected !== []) {
            $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
            $execResult['known_issues'] = array_values(array_merge($issues, array_map(
                static fn ($d) => 'User rejected command: '.$d,
                $rejected,
            )));
        }

        if ($executed !== []) {
            $execResult['_commands_executed'] = $executed;
            $execResult['git_status_after'] = $this->projectCommands->captureGitStatus();
            if ($emit !== null) {
                $this->emit($emit, $this->basePayload($run, 'commands_executed', [
                    'agent' => 'executor',
                    'status' => 'success',
                    'summary' => count($executed).' project command(s) applied after your approval.',
                    'artifacts' => [
                        'commands_executed' => $executed,
                        'git_status_after' => $execResult['git_status_after'],
                    ],
                ]));
            }
        }

        return $execResult;
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $pipeline
     * @return array{execResult: array<string, mixed>, pipeline: array<string, mixed>, paused: array<string, mixed>|null}
     */
    protected function runExecutorRevertForRejectedFiles(
        Run $run,
        array $execResult,
        array $pipeline,
        string $feedback,
        ?callable $emit,
    ): array {
        $revertPayload = ExecutorEvidenceSupport::rejectedFilesPayloadForRevision(
            $execResult,
            $run->id,
            $feedback,
        );
        if ($revertPayload === null) {
            return ['execResult' => $execResult, 'pipeline' => $pipeline, 'paused' => null];
        }

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
        $agentPrompt = (string) ($pipeline['agent_prompt'] ?? '');
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = is_array($pipeline['model_route'] ?? null) ? $pipeline['model_route'] : [];
        /** @var array<string, string> $modelsResolved */
        $modelsResolved = is_array($pipeline['models_resolved'] ?? null) ? $pipeline['models_resolved'] : [];
        /** @var array<string, mixed> $routerCtx */
        $routerCtx = is_array($pipeline['router_ctx'] ?? null) ? $pipeline['router_ctx'] : [];
        /** @var array<string, mixed> $plan */
        $plan = is_array($pipeline['plan'] ?? null) ? $pipeline['plan'] : [];
        /** @var list<array<string, mixed>> $memPayload */
        $memPayload = is_array($pipeline['mem_payload'] ?? null) ? $pipeline['mem_payload'] : [];
        $workflow = (string) ($pipeline['workflow'] ?? $modelRoute['workflow'] ?? '');
        $tokenAcc = (int) ($pipeline['token_acc'] ?? 0);
        $tRun = (float) ($pipeline['t_run'] ?? microtime(true));
        $preflightReads = is_array($pipeline['preflight_reads'] ?? null) ? $pipeline['preflight_reads'] : [];
        $execProfileKey = (string) ($pipeline['exec_profile_key'] ?? 'default');
        $skillName = (string) ($pipeline['skill_name'] ?? 'cofounder');
        /** @var array<string, mixed> $step */
        $step = is_array($pipeline['step'] ?? null) ? $pipeline['step'] : [];
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];

        $revisionStep = array_merge($step, [
            'id' => 2,
            'title' => 'Revert rejected file changes',
            'task' => 'User rejected one or more proposed file writes. Revert each listed path to its before snapshot.',
        ]);

        $this->emit($emit, $this->basePayload($run, 'executor_revert_started', [
            'status' => 'running',
            'agent' => 'executor',
            'summary' => 'Executor is reverting user-rejected file changes.',
            'message' => 'Restore each rejected path to its pre-change state.',
        ]));

        $revisionResult = $this->executor->execute(
            $revisionStep,
            $skillRow,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            null,
            $plan,
            $modelRoute,
            $execProfileKey,
            $this->projects->agentWorkspaceContext(),
            $preflightReads,
            $revertPayload,
        );
        $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
        $revisionResult = $this->ensureExecutorEvidence($run, $plan, $revisionResult, $userPrompt, $emit);
        $modelsResolved['executor'] = (string) ($revisionResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');

        $revPipeline = $this->buildExecutorPipelineSnapshot(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $routerCtx,
            $memPayload,
            $plan,
            $workflow,
            $preflightReads,
            $execProfileKey,
            $skillName,
            $revisionStep,
            $skillRow,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            $tokenAcc,
            $tRun,
            $revertPayload,
        );
        $revPipeline['exec_result'] = $revisionResult;

        $revAfter = $this->applyOrPauseForExecutorApprovals($run, $revisionResult, $revPipeline, $emit);
        if (($revAfter['awaiting_approvals'] ?? false) === true) {
            return ['execResult' => $revisionResult, 'pipeline' => $revPipeline, 'paused' => $revAfter];
        }

        if (is_array($revAfter)) {
            $revisionResult = $revAfter;
        }

        $issues = is_array($revisionResult['known_issues'] ?? null) ? $revisionResult['known_issues'] : [];
        foreach ($this->executorApprovals->rejectedFileWritesForRun($run->id) as $approval) {
            /** @var array<string, mixed> $evidence */
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $path = StringCoercion::toString($evidence['path'] ?? null, $approval->operation_description);
            $issues[] = 'User rejected file change (revert required): '.$path;
        }
        $revisionResult['known_issues'] = array_values(array_unique($issues));

        $revTok = $this->estimateTokens(json_encode($revisionResult) ?: '');
        $this->emit($emit, $this->events->executorDone(
            $run,
            $revisionResult,
            $modelsResolved['executor'] ?? '',
            (int) ($revisionResult['latency_ms'] ?? 0),
            $revTok,
            'executor_revert_done',
        ));

        $pipeline['exec_result'] = $revisionResult;
        $pipeline['models_resolved'] = $modelsResolved;

        return ['execResult' => $revisionResult, 'pipeline' => $pipeline, 'paused' => null];
    }

    protected function approvalReviewRoundsUsed(Run $run): int
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];

        return (int) ($meta['approval_review_rounds'] ?? 0);
    }

    protected function incrementApprovalReviewRounds(Run $run): void
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['approval_review_rounds'] = $this->approvalReviewRoundsUsed($run) + 1;
        $run->update(['metadata' => $meta]);
    }

    protected function canRunApprovalReviewRound(Run $run): bool
    {
        return $this->approvalReviewRoundsUsed($run) < $this->settings->maxApprovalReviewRounds();
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $pipeline
     * @return array{execResult: array<string, mixed>, pipeline: array<string, mixed>, paused: array<string, mixed>|null}
     */
    protected function runExecutorRevisionForUserCodeReview(
        Run $run,
        array $execResult,
        array $pipeline,
        string $feedback,
        ?callable $emit,
        ?string $explicitInstructions = null,
    ): array {
        if (! $this->canRunApprovalReviewRound($run)) {
            return ['execResult' => $execResult, 'pipeline' => $pipeline, 'paused' => null];
        }

        $review = $explicitInstructions !== null && trim($explicitInstructions) !== ''
            ? ['instructions' => trim($explicitInstructions), 'items' => []]
            : $this->executorApprovals->collectCodeReviewInstructions($run->id);

        if ($review['instructions'] === '') {
            return ['execResult' => $execResult, 'pipeline' => $pipeline, 'paused' => null];
        }

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
        $agentPrompt = (string) ($pipeline['agent_prompt'] ?? '');
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = is_array($pipeline['model_route'] ?? null) ? $pipeline['model_route'] : [];
        /** @var array<string, string> $modelsResolved */
        $modelsResolved = is_array($pipeline['models_resolved'] ?? null) ? $pipeline['models_resolved'] : [];
        /** @var array<string, mixed> $routerCtx */
        $routerCtx = is_array($pipeline['router_ctx'] ?? null) ? $pipeline['router_ctx'] : [];
        /** @var array<string, mixed> $plan */
        $plan = is_array($pipeline['plan'] ?? null) ? $pipeline['plan'] : [];
        /** @var list<array<string, mixed>> $memPayload */
        $memPayload = is_array($pipeline['mem_payload'] ?? null) ? $pipeline['mem_payload'] : [];
        $workflow = (string) ($pipeline['workflow'] ?? $modelRoute['workflow'] ?? '');
        $tokenAcc = (int) ($pipeline['token_acc'] ?? 0);
        $tRun = (float) ($pipeline['t_run'] ?? microtime(true));
        $preflightReads = is_array($pipeline['preflight_reads'] ?? null) ? $pipeline['preflight_reads'] : [];
        $execProfileKey = (string) ($pipeline['exec_profile_key'] ?? 'default');
        $skillName = (string) ($pipeline['skill_name'] ?? 'cofounder');
        /** @var array<string, mixed> $step */
        $step = is_array($pipeline['step'] ?? null) ? $pipeline['step'] : [];
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];

        $revisionStep = array_merge($step, [
            'id' => 2,
            'title' => 'Apply user code review',
            'task' => 'User requested changes on rejected file proposals. Apply their code review instructions and re-propose files for approval.',
        ]);

        $reviewPayload = ExecutorEvidenceSupport::userCodeReviewPayloadForRevision(
            $execResult,
            $run->id,
            $review['instructions'],
            $review['items'],
            $feedback,
            $preflightReads,
        );

        $this->emit($emit, $this->basePayload($run, 'executor_code_review_started', [
            'status' => 'running',
            'agent' => 'executor',
            'summary' => 'Executor is applying your code review instructions.',
            'message' => 'Updated proposals will be shown for approval.',
        ]));

        $revisionResult = $this->executor->execute(
            $revisionStep,
            $skillRow,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            null,
            $plan,
            $modelRoute,
            $execProfileKey,
            $this->projects->agentWorkspaceContext(),
            $preflightReads,
            $reviewPayload,
        );
        $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
        $revisionResult = $this->ensureExecutorEvidence($run, $plan, $revisionResult, $userPrompt, $emit);
        $modelsResolved['executor'] = (string) ($revisionResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');

        $this->incrementApprovalReviewRounds($run);

        $revPipeline = $this->buildExecutorPipelineSnapshot(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $routerCtx,
            $memPayload,
            $plan,
            $workflow,
            $preflightReads,
            $execProfileKey,
            $skillName,
            $revisionStep,
            $skillRow,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            $tokenAcc,
            $tRun,
            $reviewPayload,
        );
        $revPipeline['exec_result'] = $revisionResult;

        $revAfter = $this->applyOrPauseForExecutorApprovals($run, $revisionResult, $revPipeline, $emit);
        if (($revAfter['awaiting_approvals'] ?? false) === true) {
            return ['execResult' => $revisionResult, 'pipeline' => $revPipeline, 'paused' => $revAfter];
        }

        if (is_array($revAfter)) {
            $revisionResult = $revAfter;
        }

        $revTok = $this->estimateTokens(json_encode($revisionResult) ?: '');
        $this->emit($emit, $this->events->executorDone(
            $run,
            $revisionResult,
            $modelsResolved['executor'] ?? '',
            (int) ($revisionResult['latency_ms'] ?? 0),
            $revTok,
            'executor_code_review_done',
        ));

        $pipeline['exec_result'] = $revisionResult;
        $pipeline['models_resolved'] = $modelsResolved;

        return ['execResult' => $revisionResult, 'pipeline' => $pipeline, 'paused' => null];
    }
}
