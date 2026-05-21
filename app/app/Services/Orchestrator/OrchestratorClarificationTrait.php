<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Run;

trait OrchestratorClarificationTrait
{
    /**
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     * @return array<string, mixed>
     */
    public function continueRun(string $runId, array $answers, ?callable $emit = null): array
    {
        $run = Run::query()->findOrFail($runId);
        if ($run->status !== 'awaiting_input') {
            throw new \InvalidArgumentException('Run is not awaiting user input (status: '.$run->status.').');
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
        $stage = (string) ($checkpoint['stage'] ?? 'pre_execution');
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

        $meta['clarification_answers'] = $allAnswers;
        unset($meta['checkpoint']);
        $run->update(['status' => 'running', 'metadata' => $meta]);

        $this->emit($emit, $this->events->clarificationReceived($run, count($answers)));

        if ($stage === 'executor_stuck') {
            return $this->resumeFromExecutorStuck($run, $pipeline, $answerBlock, $emit);
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

        return [
            'run_id' => $run->id,
            'status' => $run->status,
            'stage' => $checkpoint['stage'] ?? null,
            'clarification' => $checkpoint['clarification'] ?? null,
            'answers' => $meta['clarification_answers'] ?? [],
        ];
    }

    protected function shouldRequireClarification(Run $run): bool
    {
        if ($this->settings->orchestratorClarificationMode() === 'off') {
            return false;
        }
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];

        return empty($meta['clarification_answers']);
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
