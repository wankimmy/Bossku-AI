<?php

namespace App\Services\Orchestrator;

/**
 * Shared conversation-history compression for the Planner, Executor, and Auditor.
 *
 * Strategy: keep the most recent KEEP_TURNS turns verbatim (capped per role) and
 * synthesise a keyword summary for any older turns. This bounds token growth for
 * long sessions without requiring a second LLM call.
 */
trait AgentConversationTrait
{
    private const KEEP_TURNS = 10;

    /** @param list<array{role: string, content: string}> $conversation */
    protected function buildConversationBlock(array $conversation): string
    {
        if ($conversation === []) {
            return '(no prior conversation — this is the first turn)';
        }

        $total = count($conversation);

        // Separate old turns from the verbatim window.
        $keepCount = min(self::KEEP_TURNS, $total);
        $oldTurns = $total > $keepCount ? array_slice($conversation, 0, $total - $keepCount) : [];
        $recentTurns = array_slice($conversation, -$keepCount);

        $lines = [];

        if ($oldTurns !== []) {
            $lines[] = $this->buildOlderTurnsSummary($oldTurns);
        }

        $offset = max(0, $total - $keepCount);
        foreach ($recentTurns as $idx => $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $cap = $role === 'assistant' ? 1200 : 800;
            $content = mb_substr((string) ($turn['content'] ?? ''), 0, $cap);
            $lines[] = '[Turn '.($offset + $idx).'] '.strtoupper($role).': '.$content;
        }

        return implode("\n\n", $lines);
    }

    /** @param list<array{role: string, content: string}> $turns */
    private function buildOlderTurnsSummary(array $turns): string
    {
        $userWords = [];
        $lastAssistantSnippet = '';

        foreach ($turns as $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $text = (string) ($turn['content'] ?? '');

            if ($role === 'user') {
                // Extract meaningful words (length >= 5, alpha-only) for topic keywords
                preg_match_all('/\b[a-zA-Z]{5,}\b/', $text, $m);
                foreach ($m[0] as $word) {
                    $lw = strtolower($word);
                    $userWords[$lw] = ($userWords[$lw] ?? 0) + 1;
                }
            } elseif ($role === 'assistant' && $text !== '') {
                $lastAssistantSnippet = mb_substr($text, 0, 150);
            }
        }

        arsort($userWords);
        $keywords = implode(', ', array_slice(array_keys($userWords), 0, 12));
        $snippet = $lastAssistantSnippet !== '' ? ' Last assistant action: '.trim($lastAssistantSnippet).'...' : '';

        return '['.count($turns).' earlier turns compressed | User topics: '.$keywords.'.'.$snippet.']';
    }
}
