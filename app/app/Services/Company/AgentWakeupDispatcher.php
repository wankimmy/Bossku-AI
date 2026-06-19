<?php

namespace App\Services\Company;

use App\Jobs\ProcessAgentWakeupRequestJob;
use App\Models\BosskuAi\AgentWakeupRequest;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\SpecialistAgent;
use App\Models\BosskuAi\WorkIssue;
use App\Services\Agents\AgentMode;
use App\Services\Agents\TaskSubagentService;
use App\Services\BosskuAi\RuntimeSettings;

class AgentWakeupDispatcher
{
    public function __construct(
        protected RuntimeSettings $settings,
        protected TaskSubagentService $subagents,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function enqueue(
        ?SpecialistAgent $agent,
        ?WorkIssue $issue,
        ?Run $run,
        string $wakeReason,
        array $context = [],
        ?string $idempotencyKey = null,
    ): AgentWakeupRequest {
        if (! $this->settings->agentWakeupsEnabled()) {
            return new AgentWakeupRequest([
                'specialist_agent_id' => $agent?->id,
                'work_issue_id' => $issue?->id,
                'run_id' => $run?->id,
                'wake_reason' => $wakeReason,
                'status' => 'skipped',
                'idempotency_key' => 'disabled',
                'skip_reason' => 'agent_wakeups_disabled',
                'context_snapshot' => $context,
                'processed_at' => now(),
            ]);
        }

        $key = $idempotencyKey ?? sha1(json_encode([
            $agent?->id,
            $issue?->id,
            $run?->id,
            $wakeReason,
            $context['task_key'] ?? null,
        ]));

        return AgentWakeupRequest::query()->firstOrCreate(
            [
                'specialist_agent_id' => $agent?->id,
                'work_issue_id' => $issue?->id,
                'wake_reason' => $wakeReason,
                'idempotency_key' => $key,
            ],
            [
                'run_id' => $run?->id,
                'status' => 'queued',
                'context_snapshot' => $context,
            ],
        );
    }

  /**
   * @return array{processed: int, skipped: int, failed: int}
   */
    public function dispatchQueued(int $limit = 10): array
    {
        if (! $this->settings->agentWakeupsEnabled() || ! $this->settings->companyStaffEnabled()) {
            return ['processed' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $requests = AgentWakeupRequest::query()
            ->where('status', 'queued')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($requests as $request) {
            try {
                $claimed = AgentWakeupRequest::query()
                    ->whereKey($request->id)
                    ->where('status', 'queued')
                    ->update(['status' => 'processing']);

                if ($claimed !== 1) {
                    $skipped++;
                    continue;
                }

                ProcessAgentWakeupRequestJob::dispatch((string) $request->id)->onQueue('agent-wakeups');
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $request->update([
                    'status' => 'failed',
                    'skip_reason' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            }
        }

        return compact('processed', 'skipped', 'failed');
    }

    public function processRequest(AgentWakeupRequest $request): string
    {
        $agent = $request->specialistAgent;
        $parentRun = $request->run;
        if ($agent === null || $parentRun === null) {
            $request->update([
                'status' => 'skipped',
                'skip_reason' => 'missing_agent_or_run',
                'processed_at' => now(),
            ]);

            return 'skipped';
        }

        $mode = (string) (is_array($agent->metadata) ? ($agent->metadata['agent_mode'] ?? AgentMode::Subagent->value) : AgentMode::Subagent->value);
        if ($mode === AgentMode::Hidden->value) {
            $request->update([
                'status' => 'skipped',
                'skip_reason' => 'hidden_agent_mode',
                'processed_at' => now(),
            ]);

            return 'skipped';
        }

        $context = is_array($request->context_snapshot) ? $request->context_snapshot : [];
        $prompt = (string) ($context['prompt'] ?? 'Continue assigned work from the wakeup queue.');

        $this->subagents->spawnChildRun($parentRun, $agent, $prompt, [
            'metadata' => [
                'wake_reason' => $request->wake_reason,
                'work_issue_id' => $request->work_issue_id,
            ],
        ]);

        $request->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        return 'processed';
    }
}
