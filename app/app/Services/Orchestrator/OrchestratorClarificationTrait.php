<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Run;

trait OrchestratorClarificationTrait
{
    /**
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     * @return array<string, mixed>
     */
    public function continueRun(
        string $runId,
        array $answers,
        ?callable $emit = null,
        string $reviewDecision = 'approve',
        ?string $codeReviewComment = null,
    ): array {
        $run = Run::query()->findOrFail($runId);
        if ($run->status !== 'awaiting_input') {
            throw new \InvalidArgumentException('Run is not awaiting user input (status: '.$run->status.').');
        }

        $reviewDecision = strtolower(trim($reviewDecision));
        if (! in_array($reviewDecision, ['approve', 'request_changes'], true)) {
            throw new \InvalidArgumentException('Invalid review_decision.');
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
        $stage = (string) ($checkpoint['stage'] ?? 'pre_execution');

        if ($stage === 'executor_approvals') {
            throw new \InvalidArgumentException(
                'Run is awaiting change approvals. POST /api/runs/'.$runId.'/continue-approvals/stream after all items are approved or rejected.',
            );
        }

        /** @var array<string, mixed> $pipeline */
        $pipeline = is_array($checkpoint['pipeline'] ?? null) ? $checkpoint['pipeline'] : [];
        /** @var list<array<string, mixed>> $questions */
        $questions = is_array($checkpoint['clarification']['questions'] ?? null)
            ? $checkpoint['clarification']['questions']
            : [];

        /** @var list<array<string, mixed>> $priorAnswers */
        $priorAnswers = is_array($meta['clarification_answers'] ?? null) ? $meta['clarification_answers'] : [];
        $allAnswers = array_merge($priorAnswers, $answers);
        $answerBlock = $this->clarification->formatAnswersBlock($allAnswers, $questions);

        if ($reviewDecision === 'request_changes') {
            $comment = trim((string) $codeReviewComment);
            if ($comment === '') {
                throw new \InvalidArgumentException('code_review_comment is required when review_decision is request_changes.');
            }
            $answerBlock = trim($answerBlock."\n\n## Code review instructions\n".$comment);
        }

        $meta['clarification_answers'] = $allAnswers;
        unset($meta['checkpoint']);
        $run->update(['status' => 'running', 'metadata' => $meta]);

        $this->emit($emit, $this->events->clarificationReceived($run, count($answers)));

        if ($reviewDecision === 'request_changes' && in_array($stage, ['executor_escalation', 'executor_stuck', 'auditor_escalation'], true)) {
            return $this->resumeFromCodeReviewRequest($run, $pipeline, $answerBlock, trim((string) $codeReviewComment), $emit);
        }

        if ($stage === 'executor_escalation') {
            return $this->resumeFromExecutorEscalation($run, $pipeline, $answerBlock, $emit);
        }

        if ($stage === 'auditor_escalation') {
            return $this->resumeFromAuditorEscalation($run, $pipeline, $answerBlock, $emit);
        }

        if ($stage === 'executor_stuck') {
            return $this->resumeFromExecutorStuck($run, $pipeline, $answerBlock, $emit);
        }

        if ($stage === 'user_local_commands') {
            return $this->resumeFromUserLocalCommands($run, $pipeline, $allAnswers, $questions, $emit);
        }

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        /** @var list<array{role: string, content: string}> $conversation */
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];
        $prompt = $this->effectivePrompt($userPrompt, $conversation);
        $prompt = trim($prompt."\n\n".$answerBlock);
        $agentPrompt = trim($prompt."\n\n".$this->projects->agentWorkspaceContext());

        /** @var array<string, mixed> $modelRoute */
        $modelRoute = is_array($pipeline['model_route'] ?? null) ? $pipeline['model_route'] : [];
        /** @var array<string, string> $modelsResolved */
        $modelsResolved = is_array($pipeline['models_resolved'] ?? null) ? $pipeline['models_resolved'] : [];
        /** @var array<string, mixed> $routerMeta */
        $routerMeta = is_array($pipeline['router_meta'] ?? null) ? $pipeline['router_meta'] : [];
        /** @var list<array<string, mixed>> $memPayload */
        $memPayload = is_array($pipeline['mem_payload'] ?? null) ? $pipeline['mem_payload'] : [];

        return $this->runPipelineAfterMemory(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $routerMeta,
            $memPayload,
            $emit,
            (int) ($pipeline['token_acc'] ?? 0),
            (float) ($pipeline['t_run'] ?? microtime(true)),
        );
    }

    public function clarificationForRun(string $runId): array
    {
        $run = Run::query()->findOrFail($runId);
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];

        $stage = $checkpoint['stage'] ?? null;
        $clarification = ($stage === 'executor_approvals') ? null : ($checkpoint['clarification'] ?? null);

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'stage' => $stage,
            'clarification' => $clarification,
            'answers' => $meta['clarification_answers'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     */
    protected function shouldRequireClarification(Run $run, string $userPrompt = '', array $modelRoute = []): bool
    {
        if ($this->settings->orchestratorClarificationMode() === 'off') {
            return false;
        }
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];

        if (! empty($meta['clarification_answers'])) {
            return false;
        }

        if ($this->settings->orchestratorClarificationMode() === 'always') {
            return true;
        }

        return $this->clarification->shouldAskForPrompt($userPrompt, $modelRoute);
    }

    /**
     * @param  array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}  $clarification
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function pauseForClarification(
        Run $run,
        array $clarification,
        string $stage,
        array $pipeline,
        ?callable $emit,
        ?string $fromAgent = null,
        ?string $origin = null,
        array $proof = [],
    ): array {
        $questions = $clarification['questions'];
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['checkpoint'] = [
            'phase' => 'awaiting_clarification',
            'stage' => $stage,
            'clarification' => [
                'questions' => $questions,
                'assumptions' => $clarification['assumptions'],
                'summary' => $clarification['summary'],
                'asked_at' => now()->toIso8601String(),
                'from_agent' => $fromAgent,
                'origin' => $origin ?? $stage,
                'proof' => $proof,
            ],
            'pipeline' => $pipeline,
        ];

        $run->update([
            'status' => 'awaiting_input',
            'metadata' => $meta,
        ]);

        $this->emit($emit, $this->events->clarificationRequested(
            $run,
            $questions,
            $stage,
            $clarification['summary'],
            $clarification['assumptions'],
            $fromAgent,
            $origin,
            $proof,
        ));

        return [
            'run_id' => $run->id,
            'status' => 'awaiting_input',
            'awaiting_clarification' => true,
            'stage' => $stage,
            'questions' => $questions,
            'summary' => $clarification['summary'],
            'assumptions' => $clarification['assumptions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>|null  $lastAudit
     */
    /**
     * @param  array<string, mixed>  $agentResult
     */
    protected function shouldPauseForAgentEscalation(Run $run, array $agentResult, string $stage): bool
    {
        if ($this->settings->orchestratorClarificationMode() === 'off') {
            return false;
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $resolved = is_array($meta['agent_escalation_resolved'] ?? null) ? $meta['agent_escalation_resolved'] : [];
        if (! empty($resolved[$stage])) {
            return false;
        }

        if ($stage === 'auditor_escalation') {
            return ($agentResult['needs_user_input'] ?? false) === true;
        }

        return ExecutorStuckDetector::wantsUserInput($agentResult);
    }

    /**
     * @param  array<string, mixed>  $agentResult
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function pauseForAgentEscalation(
        Run $run,
        string $stage,
        string $fromAgent,
        array $agentResult,
        array $pipeline,
        ?callable $emit,
    ): array {
        $questions = $this->buildEscalationQuestions($agentResult, $fromAgent);
        $proof = $this->buildAgentProof($agentResult, $fromAgent);
        $blockers = is_array($agentResult['blockers'] ?? null) ? $agentResult['blockers'] : [];
        $summary = trim(implode(' ', array_filter([
            (string) ($agentResult['summary'] ?? ''),
            (string) ($agentResult['patch_summary'] ?? ''),
            $blockers[0] ?? ExecutorStuckDetector::stuckSummary($agentResult)[0] ?? '',
        ])));

        if ($summary === '') {
            $summary = ucfirst($fromAgent).' needs your input before continuing.';
        }

        $clarification = [
            'questions' => $questions,
            'assumptions' => [],
            'ready_to_proceed' => false,
            'summary' => $summary,
        ];

        return $this->pauseForClarification(
            $run,
            $clarification,
            $stage,
            $pipeline,
            $emit,
            $fromAgent,
            $stage,
            $proof,
        );
    }

    /**
     * @param  array<string, mixed>  $agentResult
     * @return list<array<string, mixed>>
     */
    protected function buildEscalationQuestions(array $agentResult, string $fromAgent): array
    {
        $raw = is_array($agentResult['questions'] ?? null) ? $agentResult['questions'] : [];
        if ($raw !== []) {
            $normalized = $this->clarification->normalize([
                'summary' => (string) ($agentResult['summary'] ?? $agentResult['patch_summary'] ?? ''),
                'questions' => $raw,
                'ready_to_proceed' => false,
            ], $fromAgent.'_escalation', '');

            return $normalized['questions'];
        }

        $blockers = is_array($agentResult['blockers'] ?? null) ? $agentResult['blockers'] : [];
        $options = [];
        foreach (is_array($agentResult['suggested_options'] ?? null) ? $agentResult['suggested_options'] : [] as $idx => $opt) {
            if (is_string($opt) && $opt !== '') {
                $options[] = ['id' => 'opt'.($idx + 1), 'label' => $opt, 'recommendation' => $idx === 0];
            } elseif (is_array($opt)) {
                $label = (string) ($opt['label'] ?? $opt['text'] ?? '');
                if ($label !== '') {
                    $options[] = [
                        'id' => (string) ($opt['id'] ?? 'opt'.($idx + 1)),
                        'label' => $label,
                        'recommendation' => (bool) ($opt['recommendation'] ?? $idx === 0),
                    ];
                }
            }
        }

        $prompt = $blockers[0] ?? ($fromAgent === 'auditor'
            ? 'The auditor flagged a high-risk concern. How should we proceed?'
            : 'The executor cannot proceed without your decision.');

        $normalized = $this->clarification->normalize([
            'summary' => $prompt,
            'questions' => [[
                'id' => 'escalation_1',
                'prompt' => $prompt,
                'options' => $options,
            ]],
            'ready_to_proceed' => false,
        ], $fromAgent.'_escalation', '');

        return $normalized['questions'];
    }

    /**
     * @param  array<string, mixed>  $agentResult
     * @return array<string, mixed>
     */
    protected function buildAgentProof(array $agentResult, string $fromAgent): array
    {
        $proof = [
            'from_agent' => $fromAgent,
            'files_read' => $agentResult['files_read'] ?? [],
            'files_changed' => $agentResult['files_changed'] ?? [],
            'proof_files' => ExecutorEvidenceSupport::proofFilePaths($agentResult),
            'blockers' => $agentResult['blockers'] ?? [],
            'known_issues' => $agentResult['known_issues'] ?? [],
            'commands_run' => $agentResult['commands_run'] ?? [],
        ];

        if ($fromAgent === 'auditor') {
            $proof['findings'] = $agentResult['findings'] ?? [];
            $proof['required_fixes'] = $agentResult['required_fixes'] ?? [];
        }

        return $proof;
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function resumeFromExecutorEscalation(
        Run $run,
        array $pipeline,
        string $answerBlock,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $resolved = is_array($meta['agent_escalation_resolved'] ?? null) ? $meta['agent_escalation_resolved'] : [];
        $resolved['executor_escalation'] = true;
        $meta['agent_escalation_resolved'] = $resolved;
        $run->update(['metadata' => $meta]);

        return $this->resumeFromExecutorStuck($run, $pipeline, $answerBlock, $emit);
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function resumeFromAuditorEscalation(
        Run $run,
        array $pipeline,
        string $answerBlock,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $resolved = is_array($meta['agent_escalation_resolved'] ?? null) ? $meta['agent_escalation_resolved'] : [];
        $resolved['auditor_escalation'] = true;
        $meta['agent_escalation_resolved'] = $resolved;
        $run->update(['metadata' => $meta]);

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $agentPrompt = trim((string) ($pipeline['agent_prompt'] ?? '')."\n\n".$answerBlock);
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = is_array($pipeline['model_route'] ?? null) ? $pipeline['model_route'] : [];
        /** @var array<string, string> $modelsResolved */
        $modelsResolved = is_array($pipeline['models_resolved'] ?? null) ? $pipeline['models_resolved'] : [];
        /** @var array<string, mixed> $routerCtx */
        $routerCtx = is_array($pipeline['router_ctx'] ?? null) ? $pipeline['router_ctx'] : [];
        /** @var array<string, mixed> $plan */
        $plan = is_array($pipeline['plan'] ?? null) ? $pipeline['plan'] : [];
        /** @var array<string, mixed> $execResult */
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        /** @var array<string, mixed> $lastAudit */
        $lastAudit = is_array($pipeline['last_audit'] ?? null) ? $pipeline['last_audit'] : [];
        /** @var list<array<string, mixed>> $preflightReads */
        $preflightReads = is_array($pipeline['preflight_reads'] ?? null) ? $pipeline['preflight_reads'] : [];
        $execProfileKey = (string) ($pipeline['exec_profile_key'] ?? 'default');
        $skillName = (string) ($pipeline['skill_name'] ?? 'cofounder');
        /** @var array<string, mixed> $step */
        $step = is_array($pipeline['step'] ?? null) ? $pipeline['step'] : [];
        $step['task'] = $agentPrompt;
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
        $workflow = (string) ($pipeline['workflow'] ?? $modelRoute['workflow'] ?? '');
        $tokenAcc = (int) ($pipeline['token_acc'] ?? 0);
        $tRun = (float) ($pipeline['t_run'] ?? microtime(true));
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];
        /** @var list<array<string, mixed>> $memPayload */
        $memPayload = is_array($pipeline['mem_payload'] ?? null) ? $pipeline['mem_payload'] : [];
        $executorOutputs = is_array($pipeline['executor_outputs'] ?? null) ? $pipeline['executor_outputs'] : [$execResult];
        $stepNum = (int) ($pipeline['step_num'] ?? 3);

        if (
            ($lastAudit['status'] ?? '') === 'needs_revision'
            && $this->settings->maxRevisionRounds() > 0
            && ! ($execResult['needs_user_input'] ?? false)
            && ! $this->executorFailedFromLlmJson($execResult)
        ) {
            $this->emit($emit, $this->basePayload($run, 'executor_revision_started', [
                'status' => 'running',
                'agent' => 'executor',
                'from_agent' => 'auditor',
                'to_agent' => 'executor',
                'summary' => 'Executor is applying audit feedback after your guidance.',
            ]));

            $revisionStep = array_merge($step, ['id' => 2, 'title' => 'Fix audit feedback']);
            $auditFeedback = ExecutorEvidenceSupport::auditorPayloadForRevision(
                $lastAudit,
                $execResult,
                $preflightReads,
                $run->id,
            );
            $auditFeedback['original_prompt'] = $agentPrompt;
            $auditFeedback['user_guidance'] = $answerBlock;

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
                $auditFeedback,
            );
            $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
            $revisionResult = $this->ensureExecutorEvidence($run, $plan, $revisionResult, $userPrompt, $emit);
            $modelsResolved['executor'] = (string) ($revisionResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');
            $revPipeline = $pipeline;
            $revPipeline['exec_result'] = $revisionResult;
            $revAfter = $this->applyOrPauseForExecutorApprovals($run, $revisionResult, $revPipeline, $emit);
            if (($revAfter['awaiting_approvals'] ?? false) === true) {
                return $revAfter;
            }
            $revisionResult = $revAfter;
            $revTok = $this->estimateTokens(json_encode($revisionResult) ?: '');
            $tokenAcc += $revTok;
            $this->emit($emit, $this->events->executorDone($run, $revisionResult, $modelsResolved['executor'], (int) ($revisionResult['latency_ms'] ?? 0), $revTok, 'executor_revision_done'));
            $execResult = $revisionResult;
            $executorOutputs[] = $revisionResult;
        }

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
            true,
            $lastAudit,
        );
    }

    protected function shouldPauseForExecutorStuck(
        Run $run,
        array $execResult,
        ?array $lastAudit,
        int $revisionRoundsUsed,
    ): bool {
        if ($this->settings->orchestratorClarificationMode() === 'off') {
            return false;
        }
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        if (! empty($meta['executor_stuck_resolved'])) {
            return false;
        }

        return ExecutorStuckDetector::isStuck(
            $execResult,
            $lastAudit,
            $revisionRoundsUsed,
            $this->settings->maxRevisionRounds(),
        );
    }

    /**
     * @param  array<string, mixed>  $pipelineSnapshot
     * @return array<string, mixed>
     */
    protected function pauseForExecutorStuck(
        Run $run,
        array $pipelineSnapshot,
        array $execResult,
        ?callable $emit,
    ): array {
        $clarification = $this->clarification->ask(
            (string) ($pipelineSnapshot['user_prompt'] ?? $run->prompt),
            is_array($pipelineSnapshot['conversation'] ?? null) ? $pipelineSnapshot['conversation'] : [],
            is_array($pipelineSnapshot['model_route'] ?? null) ? $pipelineSnapshot['model_route'] : [],
            'executor_stuck',
            ['exec_result' => $execResult],
        );

        $pipelineSnapshot['exec_result'] = $execResult;

        return $this->pauseForClarification($run, $clarification, 'executor_stuck', $pipelineSnapshot, $emit);
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function resumeFromExecutorStuck(
        Run $run,
        array $pipeline,
        string $answerBlock,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['executor_stuck_resolved'] = true;
        $run->update(['metadata' => $meta]);

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $agentPrompt = trim((string) ($pipeline['agent_prompt'] ?? '')."\n\n".$answerBlock);
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
        $step['task'] = $agentPrompt;
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);

        $this->emit($emit, $this->basePayload($run, 'executor_step_started', [
            'status' => 'running',
            'agent' => 'executor',
            'summary' => 'Retrying executor with your guidance.',
        ]));

        $execResult = $this->executor->execute(
            $step,
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
            is_array($pipeline['audit_feedback'] ?? null) ? $pipeline['audit_feedback'] : null,
        );
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $preflightReads);
        $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);
        $modelsResolved['executor'] = (string) ($execResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');

        $retryPipeline = $pipeline;
        $retryPipeline['exec_result'] = $execResult;
        $retryAfter = $this->applyOrPauseForExecutorApprovals($run, $execResult, $retryPipeline, $emit);
        if (($retryAfter['awaiting_approvals'] ?? false) === true) {
            return $retryAfter;
        }
        $execResult = $retryAfter;

        $exTok = $this->estimateTokens(json_encode($execResult) ?: '');
        $tokenAcc += $exTok;
        $this->emit($emit, $this->events->executorDone($run, $execResult, $modelsResolved['executor'], (int) ($execResult['latency_ms'] ?? 0), $exTok, 'executor_revision_done'));

        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];

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
            [$execResult],
            3,
        );
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function resumeFromCodeReviewRequest(
        Run $run,
        array $pipeline,
        string $answerBlock,
        string $codeReviewComment,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $execResult */
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $agentPrompt = trim((string) ($pipeline['agent_prompt'] ?? '')."\n\n".$answerBlock);
        $pipeline['agent_prompt'] = $agentPrompt;

        $reviewOutcome = $this->runExecutorRevisionForUserCodeReview(
            $run,
            $execResult,
            $pipeline,
            $answerBlock,
            $emit,
            $codeReviewComment,
        );

        if ($reviewOutcome['paused'] !== null) {
            return $reviewOutcome['paused'];
        }

        $execResult = $reviewOutcome['execResult'];
        $pipeline = $reviewOutcome['pipeline'];
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
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
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  array<string, mixed>  $routerCtx
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $preflightReads
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $skillRow
     * @param  list<string>  $ruleLines
     * @param  array<string, mixed>|null  $auditFeedback
     * @return array<string, mixed>
     */
    protected function buildExecutorPipelineSnapshot(
        Run $run,
        string $userPrompt,
        string $prompt,
        string $agentPrompt,
        array $conversation,
        array $modelRoute,
        array $modelsResolved,
        array $routerCtx,
        array $memPayload,
        array $plan,
        string $workflow,
        array $preflightReads,
        string $execProfileKey,
        string $skillName,
        array $step,
        array $skillRow,
        array $ruleLines,
        string $pbExcerpt,
        string $chkExcerpt,
        int $tokenAcc,
        float $tRun,
        ?array $auditFeedback = null,
    ): array {
        return [
            'user_prompt' => $userPrompt,
            'effective_prompt' => $prompt,
            'agent_prompt' => $agentPrompt,
            'conversation' => $conversation,
            'model_route' => $modelRoute,
            'models_resolved' => $modelsResolved,
            'router_ctx' => $routerCtx,
            'mem_payload' => $memPayload,
            'plan' => $plan,
            'workflow' => $workflow,
            'preflight_reads' => $preflightReads,
            'exec_profile_key' => $execProfileKey,
            'skill_name' => $skillName,
            'step' => $step,
            'skill_row' => $skillRow,
            'rule_lines' => $ruleLines,
            'playbook_excerpt' => $pbExcerpt,
            'checklist_excerpt' => $chkExcerpt,
            'token_acc' => $tokenAcc,
            't_run' => $tRun,
            'audit_feedback' => $auditFeedback,
        ];
    }
}
