<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Agents\AgentMode;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Support\Str;

class TaskSubagentService
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function spawnChildRun(
        Run $parent,
        SpecialistAgent $agent,
        string $taskPrompt,
        array $options = [],
    ): array {
        $mode = (string) (is_array($agent->metadata) ? ($agent->metadata['agent_mode'] ?? AgentMode::Subagent->value) : AgentMode::Subagent->value);
        if ($mode === AgentMode::Hidden->value) {
            return [
                'status' => 'skipped',
                'reason' => 'hidden_agent_mode',
                'final_output' => '',
            ];
        }

        $childPrompt = '@'.$agent->role_slug.' '.$taskPrompt;

        // Background mode: start the child run but return immediately with a
        // task_id. The parent continues other work; the result is delivered
        // as a synthetic message when the child completes. Ported from
        // opencode's background subagent pattern (task.ts:98).
        if (($options['background'] ?? false) === true) {
            return $this->spawnBackground($parent, $agent, $childPrompt, $taskPrompt, $options);
        }

        return app(OrchestratorService::class)->run(
            $childPrompt,
            $options['emit'] ?? null,
            is_array($options['conversation'] ?? null) ? $options['conversation'] : [],
            array_merge($options, [
                'parent_run_id' => (string) $parent->id,
                'run_kind' => 'subagent',
                'metadata' => array_merge(is_array($options['metadata'] ?? null) ? $options['metadata'] : [], [
                    'subagent_role_slug' => $agent->role_slug,
                    'subagent_display_name' => $agent->display_name,
                    'parent_run_id' => (string) $parent->id,
                ]),
            ]),
        );
    }

    /**
     * Spawn a background child run. Returns immediately with a task_id; the
     * parent does not wait. The child runs via a queued job or async dispatch;
     * the result is stored in BackgroundJobService for later injection.
     *
     * @return array<string, mixed>
     */
    private function spawnBackground(
        Run $parent,
        SpecialistAgent $agent,
        string $childPrompt,
        string $originalTask,
        array $options,
    ): array {
        $taskId = (string) Str::uuid();

        $bgJob = app(BackgroundJobService::class);
        $bgJob->start($taskId, (string) $parent->id);

        // Dispatch the child run asynchronously. In production this would be
        // a queued job; for the synchronous test path it runs inline but the
        //task_id is still tracked.
        try {
            $result = app(OrchestratorService::class)->run(
                $childPrompt,
                $options['emit'] ?? null,
                is_array($options['conversation'] ?? null) ? $options['conversation'] : [],
                array_merge($options, [
                    'parent_run_id' => (string) $parent->id,
                    'run_kind' => 'subagent',
                    'metadata' => array_merge(is_array($options['metadata'] ?? null) ? $options['metadata'] : [], [
                        'subagent_role_slug' => $agent->role_slug,
                        'subagent_display_name' => $agent->display_name,
                        'parent_run_id' => (string) $parent->id,
                        'background_task_id' => $taskId,
                    ]),
                ]),
            );

            $output = (string) ($result['final_output'] ?? '');
            $bgJob->complete($taskId, $output);
        } catch (\Throwable $e) {
            $bgJob->fail($taskId, $e->getMessage());
        }

        return [
            'status' => 'background',
            'task_id' => $taskId,
            'subagent_display_name' => $agent->display_name,
            'subagent_role_slug' => $agent->role_slug,
            'final_output' => SubagentTaskContract::resultEnvelope(
                $taskId,
                'completed',
                $agent->display_name.' dispatched in background.',
                '',
            ),
            'instruction' => SubagentTaskContract::BACKGROUND_INSTRUCTION,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $childResults
     */
    public function summarizeForParent(string $parentPrompt, array $childResults): string
    {
        $chunks = [];
        foreach ($childResults as $result) {
            $name = (string) ($result['subagent_display_name'] ?? $result['subagent_role_slug'] ?? 'Subagent');
            $output = trim((string) ($result['final_output'] ?? $result['output'] ?? ''));
            if ($output !== '') {
                $chunks[] = "### {$name}\n{$output}";
            }
        }

        if ($chunks === []) {
            return '';
        }

        return "## Subagent results for: {$parentPrompt}\n\n".implode("\n\n", $chunks);
    }
}
