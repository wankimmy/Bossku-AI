<?php

namespace App\Services\Scm;

use App\Contracts\Scm\ScmProvider;
use App\Jobs\ResumeRunFromReactionJob;
use App\Models\BosskuAi\ReactionState;
use App\Models\BosskuAi\Run;
use App\Services\Learning\FeedbackReportService;
use Illuminate\Support\Facades\Log;

class ReactionEngine
{
    public function __construct(
        private readonly GithubScmService $github,
        private readonly FeedbackReportService $feedback,
    ) {}

    public function pollRun(Run $run): array
    {
        $meta = is_array($run->metadata) ? $run->metadata : [];
        $scm = is_array($meta['scm'] ?? null) ? $meta['scm'] : [];
        $owner = (string) ($scm['owner'] ?? '');
        $repo = (string) ($scm['repo'] ?? '');
        $pr = (int) ($scm['pull_number'] ?? 0);
        if ($owner === '' || $repo === '' || $pr < 1) {
            return ['skipped' => true, 'reason' => 'no_scm_metadata'];
        }

        $provider = $this->resolveProvider((string) ($scm['provider'] ?? 'github'));
        $actions = [];

        $ci = $provider->getCISummary($owner, $repo, $pr);
        if (($ci['state'] ?? '') === 'failed') {
            $actions[] = $this->executeReaction($run, 'ci_failed', $ci);
        }

        $review = $provider->getReviewDecision($owner, $repo, $pr);
        if (in_array(strtoupper((string) ($review['decision'] ?? '')), ['CHANGES_REQUESTED'], true)) {
            $comments = $provider->getPendingComments($owner, $repo, $pr);
            $actions[] = $this->executeReaction($run, 'changes_requested', [
                'decision' => $review['decision'],
                'comments' => $comments,
            ]);
        }

        $merge = $provider->getMergeability($owner, $repo, $pr);
        if (($merge['mergeable_state'] ?? '') === 'dirty') {
            $actions[] = $this->executeReaction($run, 'merge_conflict', $merge);
        }

        if (($ci['state'] ?? '') === 'success' && strtoupper((string) ($review['decision'] ?? '')) === 'APPROVED') {
            $actions[] = $this->executeReaction($run, 'approved_and_green', [
                'ci' => $ci,
                'review' => $review,
            ]);
        }

        return ['actions' => $actions];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function executeReaction(Run $run, string $key, array $payload): array
    {
        $config = config('bossku.reactions.'.$key, []);
        if (! is_array($config) || ($config['auto'] ?? false) !== true) {
            return ['key' => $key, 'skipped' => true, 'reason' => 'auto_disabled'];
        }

        $state = ReactionState::query()->firstOrCreate(
            ['run_id' => $run->getKey(), 'reaction_key' => $key],
            ['attempts' => 0],
        );

        $payloadHash = sha1(json_encode($payload) ?: '');
        $lastPayload = is_array($state->last_payload) ? $state->last_payload : [];
        $lastHash = sha1(json_encode($lastPayload) ?: '');
        $cooldownSeconds = (int) ($config['cooldown_seconds'] ?? 300);
        if (
            $lastHash === $payloadHash
            && $state->last_triggered_at !== null
            && $state->last_triggered_at->gt(now()->subSeconds($cooldownSeconds))
        ) {
            return ['key' => $key, 'skipped' => true, 'reason' => 'cooldown_same_payload'];
        }

        $maxRetries = (int) ($config['retries'] ?? 2);
        $escalateAfter = (int) ($config['escalate_after_attempts'] ?? 3);
        if ($state->attempts >= $maxRetries) {
            if ($state->escalated_at === null && $state->attempts >= $escalateAfter) {
                $state->update(['escalated_at' => now()]);
            }

            return ['key' => $key, 'skipped' => true, 'reason' => 'retry_budget_exhausted'];
        }

        $state->update([
            'attempts' => $state->attempts + 1,
            'last_triggered_at' => now(),
            'last_payload' => $payload,
        ]);

        $action = (string) ($config['action'] ?? 'notify');
        if ($action === 'resume_run') {
            $meta = is_array($run->metadata) ? $run->metadata : [];
            $run->update([
                'metadata' => array_merge($meta, [
                    'reaction_resume' => [
                        'key' => $key,
                        'payload' => $payload,
                        'at' => now()->toIso8601String(),
                    ],
                ]),
            ]);
            $this->feedback->recordFromReaction($run, $key, $payload);
            ResumeRunFromReactionJob::dispatch((string) $run->getKey(), $key, $payload);
        }

        Log::info('bossku.reaction.executed', [
            'run_id' => $run->getKey(),
            'key' => $key,
            'action' => $action,
        ]);

        return ['key' => $key, 'action' => $action, 'attempt' => $state->attempts];
    }

    protected function resolveProvider(string $id): ScmProvider
    {
        return match ($id) {
            'github' => $this->github,
            default => $this->github,
        };
    }
}
