<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Run;

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
        $commandPending = $this->executorApprovals->proposeCommands(
            $run->id,
            is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : [],
        );
        $pending = array_values(array_merge($pending, $commandPending));

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
            throw new \InvalidArgumentException('Run still has pending approvals.');
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

        $this->emit($emit, $this->events->approvalRequested(
            $run,
            $pendingIds,
            $first !== null ? $this->executorApprovals->serializeApproval($first) : null,
            count($pendingIds),
        ));

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
}
