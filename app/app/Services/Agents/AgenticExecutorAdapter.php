<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\ToolCall;
use App\Support\StringCoercion;

/**
 * Adapts the {@see AgenticToolLoop} to the pipeline executor contract.
 *
 * The single-shot executor returns one JSON plan that the orchestrator then
 * applies and audits. The agentic loop instead *performs* the work (read → edit
 * → run tests → fix) through the governed ToolRegistry, so by the time it
 * finishes the files are already written and the commands already run.
 *
 * This adapter runs the loop, then reconstructs an executor-shaped result from
 * the durable record of what the loop did — applied file-write approvals and
 * run_command tool calls for the run — and flags it `_files_already_applied` /
 * `_commands_already_run` so the orchestrator's apply steps no-op while the
 * auditor, evidence, and revise machinery downstream run exactly as before.
 */
class AgenticExecutorAdapter
{
    public function __construct(protected AgenticToolLoop $loop) {}

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @param  list<array<string, mixed>>  $preflightReads
     * @return array<string, mixed>
     */
    public function execute(
        array $step,
        array $plan,
        array $modelRoute,
        string $execProfileKey,
        ?string $runId,
        ?callable $emit = null,
        array $preflightReads = [],
    ): array {
        $t0 = microtime(true);

        $loopResult = $this->loop->run($this->buildTask($step, $plan, $preflightReads), [
            'role' => 'executor',
            'run_id' => $runId,
            'emit' => $emit,
        ]);

        $latency = (int) round((microtime(true) - $t0) * 1000);

        $filesChanged = $runId !== null ? $this->collectFilesChanged($runId) : [];
        $commandsRun = $runId !== null ? $this->collectCommands($runId) : [];
        [$testsResult, $testsRun] = $this->deriveTests($commandsRun);

        $final = $loopResult['final'];
        $summary = is_array($final)
            ? StringCoercion::toString($final['summary'] ?? null, '')
            : StringCoercion::toString($final, '');
        if ($summary === '') {
            $summary = 'Agentic executor ran '.$loopResult['iterations'].' iteration(s).';
        }

        return [
            'step_id' => $step['id'] ?? null,
            'status' => $this->mapStatus($loopResult['status']),
            'files_read' => [],
            'files_changed' => $filesChanged,
            'commands_run' => $commandsRun,
            'tests_run' => $testsRun,
            'tests_result' => $testsResult,
            'patch_summary' => $summary,
            'known_issues' => $this->knownIssues($loopResult['status']),
            'needs_user_input' => false,
            'questions' => [],
            'blockers' => [],
            'suggested_options' => [],
            'needs_audit' => true,
            'handoff_message' => $summary,
            'executor_questions' => [],
            'memory_lessons_applied' => [],
            'checklist_status' => [],
            '_executor_model' => StringCoercion::toString($loopResult['model_used'] ?? null, ''),
            '_agentic' => true,
            '_agentic_status' => $loopResult['status'],
            '_agentic_iterations' => $loopResult['iterations'],
            '_files_already_applied' => true,
            '_commands_already_run' => true,
            'latency_ms' => $latency,
        ];
    }

    private function mapStatus(string $loopStatus): string
    {
        return match ($loopStatus) {
            'completed' => 'success',
            'error' => 'failed',
            default => 'partial',
        };
    }

    /** @return list<string> */
    private function knownIssues(string $loopStatus): array
    {
        return match ($loopStatus) {
            'stuck' => ['Agentic loop stopped: repeated the same tool call without progress.'],
            'max_iterations' => ['Agentic loop hit the iteration cap before completing.'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $preflightReads
     */
    private function buildTask(array $step, array $plan, array $preflightReads): string
    {
        $parts = [StringCoercion::toString($step['task'] ?? $plan['summary'] ?? null, 'Implement the plan.')];

        $targets = is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [];
        if ($targets !== []) {
            $parts[] = "\nTarget files:\n".implode("\n", array_map(static fn ($p) => '- '.(is_array($p) ? ($p['path'] ?? json_encode($p)) : $p), $targets));
        }

        $checklist = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        if ($checklist !== []) {
            $parts[] = "\nChecklist:\n".implode("\n", array_map(static fn ($c) => '- '.(is_array($c) ? ($c['item'] ?? $c['task'] ?? json_encode($c)) : $c), $checklist));
        }

        $readPaths = array_values(array_filter(array_map(
            static fn ($r) => is_array($r) ? StringCoercion::toString($r['path'] ?? null) : '',
            $preflightReads,
        )));
        if ($readPaths !== []) {
            $parts[] = "\nFiles already located (read them as needed):\n".implode("\n", array_map(static fn ($p) => '- '.$p, array_slice($readPaths, 0, 20)));
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<array{path: string, change_type: string, summary: string, why: string, diff: string|null}>
     */
    private function collectFilesChanged(string $runId): array
    {
        $approvals = Approval::query()
            ->where('run_id', $runId)
            ->where('operation_type', 'file_write')
            ->orderBy('created_at')
            ->get();

        $byPath = [];
        foreach ($approvals as $approval) {
            $evidence = is_array($approval->evidence) ? $approval->evidence : [];
            $path = StringCoercion::toString($evidence['path'] ?? null);
            if ($path === '') {
                continue;
            }
            // Keep only the latest change per path (loops may edit a file repeatedly).
            $byPath[$path] = [
                'path' => $path,
                'change_type' => StringCoercion::toString($evidence['change_type'] ?? null, 'modified'),
                'summary' => StringCoercion::toString($evidence['summary'] ?? null),
                'why' => '',
                'diff' => is_string($evidence['diff'] ?? null) ? $evidence['diff'] : null,
            ];
        }

        return array_values($byPath);
    }

    /**
     * @return list<array{command: string, status: string, exit_code: int|null, output_summary: string}>
     */
    private function collectCommands(string $runId): array
    {
        $calls = ToolCall::query()
            ->where('run_id', $runId)
            ->where('tool', 'run_command')
            ->orderBy('created_at')
            ->get();

        $out = [];
        foreach ($calls as $call) {
            $result = is_array($call->result) ? $call->result : [];
            $command = StringCoercion::toString($result['command'] ?? ($call->payload['command'] ?? null));
            if ($command === '') {
                continue;
            }
            $ok = (bool) ($result['ok'] ?? false);
            $out[] = [
                'command' => $command,
                'status' => $ok ? 'completed' : 'failed',
                'exit_code' => isset($result['exit_code']) ? (int) $result['exit_code'] : null,
                'output_summary' => StringCoercion::toString(
                    $result['stderr'] ?? $result['reason'] ?? $result['stdout'] ?? null,
                    '',
                ),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{command: string, status: string, exit_code: int|null, output_summary: string}>  $commands
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function deriveTests(array $commands): array
    {
        $testLike = array_values(array_filter(
            $commands,
            static fn (array $c): bool => (bool) preg_match('/\b(test|phpunit|pest|vitest|jest)\b/i', $c['command']),
        ));
        if ($testLike === []) {
            return ['not_run', []];
        }

        $allPassed = ! in_array(false, array_map(static fn ($c) => $c['status'] === 'completed', $testLike), true);

        return [$allPassed ? 'passed' : 'failed', array_map(static fn ($c) => [
            'name' => $c['command'],
            'status' => $c['status'] === 'completed' ? 'passed' : 'failed',
            'summary' => $c['output_summary'],
        ], $testLike)];
    }
}
