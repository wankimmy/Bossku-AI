<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;

/**
 * Heuristics for when the executor pipeline should pause and ask the user (legacy stuck path).
 * Explicit needs_user_input is handled by pauseForAgentEscalation; this covers hard blockers and exhausted revisions.
 */
class ExecutorStuckDetector
{
    /** @var list<string> */
    private const HARD_BLOCKER_PATTERNS = [
        'permission denied',
        'path not found',
        'file not found',
        'ambiguous file',
        'destructive',
        'without consent',
        'cannot proceed',
    ];

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
        if (self::hasHardBlocker($execResult)) {
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
     * @param  array<string, mixed>  $agentResult
     */
    public static function hasHardBlocker(array $agentResult): bool
    {
        $texts = [];
        foreach (is_array($agentResult['known_issues'] ?? null) ? $agentResult['known_issues'] : [] as $issue) {
            $texts[] = is_string($issue) ? $issue : StringCoercion::toString($issue);
        }
        foreach (is_array($agentResult['blockers'] ?? null) ? $agentResult['blockers'] : [] as $blocker) {
            $texts[] = is_string($blocker) ? $blocker : StringCoercion::toString($blocker);
        }

        foreach ($texts as $text) {
            $lower = strtolower(trim($text));
            if ($lower === '') {
                continue;
            }
            foreach (self::HARD_BLOCKER_PATTERNS as $pattern) {
                if (str_contains($lower, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $agentResult
     */
    public static function wantsUserInput(array $agentResult): bool
    {
        return ($agentResult['needs_user_input'] ?? false) === true
            || self::hasHardBlocker($agentResult);
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
        if (self::hasHardBlocker($execResult)) {
            $lines[] = 'Executor hit a hard blocker that needs your decision.';
        }
        foreach ($execResult['blockers'] ?? [] as $blocker) {
            if (is_string($blocker) && $blocker !== '') {
                $lines[] = $blocker;
            }
        }
        foreach ($execResult['known_issues'] ?? [] as $issue) {
            $text = is_string($issue) ? $issue : StringCoercion::toString($issue);
            if ($text !== '' && self::hasHardBlocker(['known_issues' => [$text]])) {
                $lines[] = $text;
            }
        }
        if ($lastAudit !== null && ($lastAudit['status'] ?? '') === 'needs_revision') {
            $lines[] = 'Auditor still needs revision: '.StringCoercion::toString($lastAudit['summary'] ?? null);
        }

        return $lines !== [] ? $lines : ['Executor could not complete the task without your decision.'];
    }
}
