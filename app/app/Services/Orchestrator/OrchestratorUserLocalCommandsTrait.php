<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Run;
use App\Support\StringCoercion;
use App\Services\Orchestrator\ExecutorEvidenceSupport;

trait OrchestratorUserLocalCommandsTrait
{
    /**
     * @param  list<array<string, mixed>>  $executed
     * @return list<array{command: string, reason: string}>
     */
    protected function commandsRequiringUserRun(array $executed): array
    {
        $out = [];
        foreach ($executed as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! $this->rowRequiresUserRun($row)) {
                continue;
            }
            $command = trim(StringCoercion::toString($row['command'] ?? null, ''));
            if ($command === '') {
                continue;
            }
            $out[] = ['command' => $command, 'reason' => $this->describeUserRunReason($row)];
        }

        return $out;
    }

    /**
     * A failed command should be handed to the user only when Bossku physically
     * cannot run it in the container: a missing binary (exit 127 / "command not
     * found"), Docker being unavailable, or project commands disabled. Genuine
     * failures (failing tests, lint, build errors, registry/network errors)
     * stay with the agent to fix.
     *
     * @param  array<string, mixed>  $row
     */
    protected function rowRequiresUserRun(array $row): bool
    {
        if (($row['ok'] ?? false) === true) {
            return false;
        }

        if ((int) ($row['exit_code'] ?? 0) === 127) {
            return true;
        }

        $reason = trim(StringCoercion::toString($row['reason'] ?? $row['stderr'] ?? null, ''));

        return $this->reasonRequiresUserRun($reason)
            || $this->reasonIndicatesMissingBinary($reason);
    }

    protected function reasonIndicatesMissingBinary(string $reason): bool
    {
        if ($reason === '') {
            return false;
        }

        // Shell "command not found" signatures only — not generic non-zero
        // exits like npm registry "404 Not Found" or bundler "Module not found".
        return (bool) preg_match(
            '/(?:command not found|: not found\b|no such file or directory|not recognized as an internal or external command|executable file not found)/i',
            $reason,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function describeUserRunReason(array $row): string
    {
        $raw = trim(StringCoercion::toString($row['reason'] ?? $row['stderr'] ?? null, ''));

        if ((int) ($row['exit_code'] ?? 0) === 127 || $this->reasonIndicatesMissingBinary($raw)) {
            return 'Not available in the Bossku container (exit 127): '.($raw !== '' ? $raw : 'command not found');
        }

        return $raw !== '' ? $raw : 'Command could not run inside Bossku.';
    }

    protected function reasonRequiresUserRun(string $reason): bool
    {
        $lower = strtolower($reason);
        if (str_contains($lower, 'docker.sock')) {
            return true;
        }
        if (str_contains($lower, 'docker compose requires')) {
            return true;
        }
        if (str_contains($lower, 'auto_execute_project_commands disabled')) {
            return true;
        }
        if (str_contains($lower, 'runs in docker')) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $commands
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    protected function buildUserLocalCommandsClarification(array $commands): array
    {
        $questions = [];
        foreach (array_values($commands) as $idx => $command) {
            $command = trim($command);
            if ($command === '') {
                continue;
            }
            $n = $idx + 1;
            $questions[] = [
                'id' => 'user_cmd_'.$n,
                'prompt' => "Run this command on your machine — Bossku can't run it inside its container (the tool isn't installed there, or it needs Docker/host access):\n\n{$command}",
                'why_it_matters' => 'Paste the full terminal output below so the agent can analyze it and continue the run.',
                'options' => [
                    [
                        'id' => 'pasted',
                        'label' => 'I ran it — output is in the box below',
                        'recommendation' => true,
                    ],
                ],
                'allow_free_text' => true,
            ];
        }

        $count = count($questions);

        return [
            'questions' => $questions,
            'assumptions' => [
                'Commands must run on your host (same machine as Docker Desktop), not inside the Bossku backend container.',
            ],
            'ready_to_proceed' => false,
            'summary' => $count === 1
                ? 'Bossku could not run 1 command in its container. Please run it locally and paste the output.'
                : "Bossku could not run {$count} commands in its container. Please run each locally and paste the output.",
        ];
    }

    /**
     * @param  array<string, mixed>  $execResult
     */
    protected function shouldPauseForUserLocalCommands(Run $run, array $execResult): bool
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        if (! empty($meta['user_local_commands_resolved'])) {
            return false;
        }

        $executed = is_array($execResult['_commands_executed'] ?? null) ? $execResult['_commands_executed'] : [];

        return $this->commandsRequiringUserRun($executed) !== [];
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    protected function maybePauseForUserLocalCommands(
        Run $run,
        array $execResult,
        array $pipeline,
        ?callable $emit,
    ): array {
        if (! $this->shouldPauseForUserLocalCommands($run, $execResult)) {
            return $execResult;
        }

        $executed = is_array($execResult['_commands_executed'] ?? null) ? $execResult['_commands_executed'] : [];
        $needsUser = $this->commandsRequiringUserRun($executed);
        $commands = array_map(static fn (array $row) => $row['command'], $needsUser);
        $pipeline['exec_result'] = $execResult;

        return $this->pauseForUserLocalCommands($run, $commands, $pipeline, $emit, 'executor', $needsUser);
    }

    /**
     * @param  list<string>  $commands
     * @param  array<string, mixed>  $pipeline
     * @param  list<array{command: string, reason: string}>  $blocked
     * @return array<string, mixed>
     */
    protected function pauseForUserLocalCommands(
        Run $run,
        array $commands,
        array $pipeline,
        ?callable $emit,
        string $fromAgent = 'orchestrator',
        array $blocked = [],
    ): array {
        $clarification = $this->buildUserLocalCommandsClarification($commands);
        $proof = [
            'from_agent' => $fromAgent,
            'needs_user_input' => true,
            'commands_run' => $blocked,
            'blockers' => array_map(
                static fn (array $row) => $row['command'].': '.$row['reason'],
                $blocked,
            ),
            'checklist_status' => $this->userLocalCommandChecklistStatus($pipeline, $blocked),
        ];

        return $this->pauseForClarification(
            $run,
            $clarification,
            'user_local_commands',
            $pipeline,
            $emit,
            $fromAgent,
            'user_local_commands',
            $proof,
        );
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @param  list<array{command: string, reason: string}>  $blocked
     * @return list<array<string, string>>
     */
    protected function userLocalCommandChecklistStatus(array $pipeline, array $blocked): array
    {
        $plan = is_array($pipeline['plan'] ?? null) ? $pipeline['plan'] : [];
        $planChecklist = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        $execChecklist = is_array($execResult['checklist_status'] ?? null) ? $execResult['checklist_status'] : [];
        $commands = implode(', ', array_map(
            static fn (array $row) => $row['command'],
            $blocked,
        ));
        $notes = $commands !== ''
            ? 'Awaiting local command output before executor can verify this item: '.$commands
            : 'Awaiting local command output before executor can verify this item.';

        $rows = [];
        foreach ($planChecklist as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            $owner = strtolower(StringCoercion::toString($item['owner'] ?? null, 'executor'));
            if ($owner !== '' && ! str_contains($owner, 'executor')) {
                continue;
            }

            $id = StringCoercion::toString($item['id'] ?? null, 'plan-'.($idx + 1));
            $rows[] = [
                'id' => $id,
                'title' => StringCoercion::toString($item['title'] ?? $item['description'] ?? null, $id),
                'owner' => StringCoercion::toString($item['owner'] ?? null, 'executor'),
                'status' => 'awaiting_input',
                'notes' => $notes,
            ];
        }

        if ($rows !== []) {
            return $rows;
        }

        foreach ($execChecklist as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = StringCoercion::toString($item['id'] ?? null, 'executor-checklist-'.($idx + 1));
            $rows[] = [
                'id' => $id,
                'title' => StringCoercion::toString($item['title'] ?? $item['description'] ?? null, $id),
                'owner' => StringCoercion::toString($item['owner'] ?? null, 'executor'),
                'status' => 'awaiting_input',
                'notes' => $notes,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     * @param  list<array<string, mixed>>  $questions
     * @return string
     */
    protected function formatUserLocalCommandAnswerBlock(array $answers, array $questions): string
    {
        $byId = [];
        foreach ($answers as $answer) {
            $byId[(string) ($answer['question_id'] ?? '')] = $answer;
        }

        $lines = ['## User-provided command output (run locally outside Docker)'];
        foreach ($questions as $q) {
            if (! is_array($q)) {
                continue;
            }
            $qid = (string) ($q['id'] ?? '');
            $prompt = (string) ($q['prompt'] ?? '');
            $command = $prompt;
            if (str_contains($prompt, "\n\n")) {
                $parts = explode("\n\n", $prompt, 2);
                $command = trim($parts[1] ?? $prompt);
            }
            $text = trim(StringCoercion::toString($byId[$qid]['free_text'] ?? null, ''));
            if ($text === '') {
                continue;
            }
            $lines[] = '### Command';
            $lines[] = '```bash';
            $lines[] = $command;
            $lines[] = '```';
            $lines[] = '### Output';
            $lines[] = '```';
            $lines[] = $text;
            $lines[] = '```';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     * @param  list<array<string, mixed>>  $questions
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function mergeUserCommandOutputsIntoExecResult(
        array $execResult,
        array $answers,
        array $questions,
    ): array {
        $byId = [];
        foreach ($answers as $answer) {
            $byId[(string) ($answer['question_id'] ?? '')] = $answer;
        }

        $executed = is_array($execResult['_commands_executed'] ?? null) ? $execResult['_commands_executed'] : [];
        $merged = [];

        foreach ($executed as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['ok'] ?? false) === true) {
                $merged[] = $row;

                continue;
            }
            $command = trim(StringCoercion::toString($row['command'] ?? null, ''));
            $reason = StringCoercion::toString($row['reason'] ?? $row['stderr'] ?? null, '');
            if ($command === '' || ! $this->rowRequiresUserRun($row)) {
                $merged[] = $row;

                continue;
            }

            $userOutput = '';
            foreach ($questions as $qIdx => $q) {
                if (! is_array($q)) {
                    continue;
                }
                $qid = (string) ($q['id'] ?? 'user_cmd_'.($qIdx + 1));
                $prompt = (string) ($q['prompt'] ?? '');
                $qCommand = $prompt;
                if (str_contains($prompt, "\n\n")) {
                    $parts = explode("\n\n", $prompt, 2);
                    $qCommand = trim($parts[1] ?? '');
                }
                if ($qCommand !== $command) {
                    continue;
                }
                $userOutput = trim(StringCoercion::toString($byId[$qid]['free_text'] ?? null, ''));
                break;
            }

            if ($userOutput === '') {
                $merged[] = $row;

                continue;
            }

            $merged[] = [
                'command' => $command,
                'exit_code' => 0,
                'stdout' => $userOutput,
                'stderr' => '',
                'ok' => true,
                'user_provided' => true,
                'previous_reason' => $reason,
            ];
        }

        $execResult['_commands_executed'] = $merged;
        $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
        $execResult['known_issues'] = array_values(array_filter(
            $issues,
            static function ($issue) {
                $text = is_string($issue) ? $issue : StringCoercion::toString($issue);

                return ! str_contains(strtolower($text), 'docker.sock');
            },
        ));

        if (($execResult['status'] ?? '') === 'partial') {
            $stillBad = array_filter($merged, static fn ($r) => is_array($r) && ($r['ok'] ?? false) !== true);
            if ($stillBad === []) {
                $execResult['status'] = 'success';
            }
        }

        $patch = StringCoercion::toString($execResult['patch_summary'] ?? null, '');
        $execResult['patch_summary'] = trim($patch."\n\nUser ran blocked Docker/host commands locally; output merged for auditor.");

        return $execResult;
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    protected function resumeFromUserLocalCommands(
        Run $run,
        array $pipeline,
        array $answers,
        array $questions,
        ?callable $emit,
    ): array {
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['user_local_commands_resolved'] = true;
        $run->update(['metadata' => $meta, 'status' => 'running']);

        $answerBlock = $this->formatUserLocalCommandAnswerBlock($answers, $questions);
        /** @var array<string, mixed> $execResult */
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        $execResult = $this->mergeUserCommandOutputsIntoExecResult($execResult, $answers, $questions);

        $userPrompt = (string) ($pipeline['user_prompt'] ?? $run->prompt);
        $prompt = (string) ($pipeline['effective_prompt'] ?? $userPrompt);
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
        /** @var array<string, mixed> $skillRow */
        $skillRow = is_array($pipeline['skill_row'] ?? null) ? $pipeline['skill_row'] : ['name' => $skillName, 'content' => ''];
        $ruleLines = is_array($pipeline['rule_lines'] ?? null) ? $pipeline['rule_lines'] : [];
        $pbExcerpt = (string) ($pipeline['playbook_excerpt'] ?? '');
        $chkExcerpt = (string) ($pipeline['checklist_excerpt'] ?? '');
        $conversation = is_array($pipeline['conversation'] ?? null) ? $pipeline['conversation'] : [];
        $executorOutputs = is_array($pipeline['executor_outputs'] ?? null) ? $pipeline['executor_outputs'] : [$execResult];
        $stepNum = (int) ($pipeline['step_num'] ?? 3);

        $this->projectCommands->logDockerAvailability();

        // Re-run the executor with the user's command outputs in context.
        // The original executor run was interrupted before it could write files (it was waiting for
        // docker/host commands to succeed). Now that those outputs are available, the executor needs
        // to complete its work (e.g. write config files, apply changes) rather than jump to auditor
        // with the stale partial exec result.
        $step['task'] = $agentPrompt;

        $this->emit($emit, $this->basePayload($run, 'executor_step_started', [
            'status' => 'running',
            'agent' => 'executor',
            'summary' => 'Executor is applying the plan.',
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
            null,
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
        $this->emit($emit, $this->events->executorDone($run, $execResult, $modelsResolved['executor'] ?? '', (int) ($execResult['latency_ms'] ?? 0), $exTok, 'executor_revision_done'));

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
}
