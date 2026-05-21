<?php

namespace App\Services\Orchestrator;

/**
 * Heuristics for when the executor pipeline should pause and ask the user.
 */
class ExecutorStuckDetector
{
    /**
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>|null  $lastAudit
     */
    public static function isStuck(
        array $execResult,
        ?array $lastAudit = null,
        int $revisionRoundsUsed = 0,
        int $maxRevisionRounds = 1,
    ): bool {
        if (($execResult['needs_user_input'] ?? false) === true) {
            return true;
        }

        $status = (string) ($execResult['status'] ?? '');
        if ($status === 'failed') {
            return true;
        }

        $blockers = $execResult['blockers'] ?? [];
        if (is_array($blockers) && $blockers !== []) {
            return true;
        }

        $knownIssues = $execResult['known_issues'] ?? [];
        $filesChanged = $execResult['files_changed'] ?? [];
        if ($status === 'partial' && $filesChanged === [] && is_array($knownIssues) && $knownIssues !== []) {
            return true;
        }

        if ($lastAudit !== null
            && ($lastAudit['status'] ?? '') === 'needs_revision'
            && $revisionRoundsUsed >= $maxRevisionRounds) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return list<string>
     */
    public static function stuckSummary(array $execResult, ?array $lastAudit = null): array
    {
        $lines = [];
        if (($execResult['needs_user_input'] ?? false) === true) {
            $lines[] = 'Executor requested user input before continuing.';
        }
        if (($execResult['status'] ?? '') === 'failed') {
            $lines[] = 'Executor reported failure: '.implode('; ', array_map('strval', $execResult['known_issues'] ?? ['unknown']));
        }
        foreach ($execResult['blockers'] ?? [] as $blocker) {
            if (is_string($blocker) && $blocker !== '') {
                $lines[] = $blocker;
            }
        }
        if ($lastAudit !== null && ($lastAudit['status'] ?? '') === 'needs_revision') {
            $lines[] = 'Auditor still needs revision: '.(string) ($lastAudit['summary'] ?? '');
        }

        return $lines !== [] ? $lines : ['Executor could not complete the task without your decision.'];
    }
}
