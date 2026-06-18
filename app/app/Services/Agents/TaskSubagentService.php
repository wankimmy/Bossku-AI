<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Agents\AgentMode;
use App\Services\Orchestrator\OrchestratorService;

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
