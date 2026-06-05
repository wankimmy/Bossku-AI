<?php

namespace App\Jobs;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResumeRunFromReactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $runId,
        public readonly string $reactionKey,
        /** @var array<string, mixed> */
        public readonly array $payload,
    ) {}

    public function handle(OrchestratorService $orchestrator): void
    {
        $run = Run::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        $summary = $this->buildReactionPrompt($this->reactionKey, $this->payload);
        $run->update(['status' => 'running']);

        try {
            $orchestrator->run($summary, null, [], [
                'existing_run_id' => (string) $run->getKey(),
                'run_kind' => $run->run_kind ?: 'standard',
                'use_worktree' => true,
                'metadata' => array_merge(is_array($run->metadata) ? $run->metadata : [], [
                    'reaction_resume_job' => [
                        'key' => $this->reactionKey,
                        'at' => now()->toIso8601String(),
                    ],
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('bossku.reaction.resume_failed', [
                'run_id' => $run->getKey(),
                'reaction' => $this->reactionKey,
                'error' => $e->getMessage(),
            ]);
            $run->update(['status' => 'failed']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildReactionPrompt(string $key, array $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';

        return match ($key) {
            'ci_failed' => "bossku, CI failed on the linked PR. Use the CI summary below, fix the codebase in this worktree, run verification, and summarize what you changed.\n\n".$encoded,
            'changes_requested' => "bossku, a reviewer requested changes on the linked PR. Address the review comments below in this worktree.\n\n".$encoded,
            'merge_conflict' => "bossku, the linked PR has merge conflicts. Resolve conflicts safely in this worktree.\n\n".$encoded,
            default => "bossku, external SCM reaction ({$key}). Continue the task using this context:\n\n".$encoded,
        };
    }
}
