<?php

namespace App\Support;

use App\Services\BosskuAi\RepoTaskDetector;

/**
 * Resolves multi-turn task intent: routing input, anaphoric follow-ups, and durable anchors.
 */
class TaskContextResolver
{
    private const MAX_PROMPT_CHARS = 12_000;

    private const MAX_TURNS = 40;

    /** @var list<string> */
    private const ANAPHORIC_PATTERNS = [
        '/^(ok|okay|yes|yep|yeah|sure|go ahead|continue|proceed|do it|read it|execute it|run it)\b/i',
        '/\b(read|open|execute|run|implement|continue with|proceed with)\s+(it|that|this|the spec|the file|the doc)\b/i',
        '/\b(ok|okay)\s+(read|execute|run|open|proceed)\b/i',
    ];

    /** @var list<string> */
    private const SOCIAL_ONLY = [
        '/^(ok|okay|thanks|thank you|thx|ty|cheers|got it|cool|nice|great|awesome)\s*[!?.]*$/i',
    ];

    /**
     * Choose classifier input: meta questions use current turn only; anaphoric follow-ups use full context.
     *
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public static function routingInput(string $userPrompt, string $routingPrompt, array $conversation = []): string
    {
        if (PromptContextHelper::isMetaAboutAssistant($userPrompt)) {
            return trim($userPrompt);
        }

        if ($conversation !== [] && self::isAnaphoricFollowUp($userPrompt, $conversation)) {
            return trim($routingPrompt !== '' ? $routingPrompt : $userPrompt);
        }

        if ($conversation !== [] && self::extractRepoLikePaths($routingPrompt) !== []) {
            $current = trim(PromptContextHelper::currentRequest($userPrompt));
            if ($current !== '' && self::isLikelyContinuationCue($current)) {
                return trim($routingPrompt);
            }
        }

        return trim($userPrompt);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public static function isAnaphoricFollowUp(string $userPrompt, array $conversation): bool
    {
        if ($conversation === []) {
            return false;
        }

        if (self::isSocialAcknowledgement($userPrompt)) {
            return false;
        }

        $current = trim(PromptContextHelper::currentRequest($userPrompt));
        if ($current === '') {
            return false;
        }

        foreach (self::ANAPHORIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $current) === 1) {
                return true;
            }
        }

        return self::isLikelyContinuationCue($current) && self::extractRepoLikePaths(self::conversationText($conversation)) !== [];
    }

    public static function isSocialAcknowledgement(string $userPrompt): bool
    {
        $current = trim(PromptContextHelper::currentRequest($userPrompt));
        if ($current === '') {
            return false;
        }

        foreach (self::SOCIAL_ONLY as $pattern) {
            if (preg_match($pattern, $current) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isLikelyContinuationCue(string $text): bool
    {
        $text = mb_strtolower(trim($text));

        return (bool) preg_match(
            '/\b(proceed|continue|go ahead|do it|read it|execute it|run it|yes|yep|sure|ok read|okay read|ok execute|proceed with)\b/u',
            $text,
        );
    }

    /**
     * Build conversation-aware prompt with newest turns first so recency wins under the char cap.
     *
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public static function buildEffectivePrompt(string $userPrompt, array $conversation): string
    {
        $userPrompt = trim($userPrompt);
        if ($conversation === []) {
            return $userPrompt;
        }

        $recentSlice = array_slice($conversation, -self::MAX_TURNS);
        $lines = [];
        $used = 0;

        foreach (array_reverse($recentSlice) as $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $line = ($role === 'assistant' ? 'Assistant' : 'User').': '.$content;
            if ($used + strlen($line) > self::MAX_PROMPT_CHARS) {
                continue;
            }
            array_unshift($lines, $line);
            $used += strlen($line);
        }

        if ($lines === []) {
            return $userPrompt;
        }

        return "Previous conversation:\n".implode("\n\n", $lines)."\n\nCurrent request:\n".$userPrompt;
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @return array{
     *   last_actionable_user_intent: string,
     *   target_paths: list<string>,
     *   docs_targets: list<string>,
     *   attachment_refs: list<string>,
     *   active_repo: string|null,
     *   task_kind: string,
     *   safety_constraints: list<string>
     * }
     */
    public static function extractContextAnchors(
        string $userPrompt,
        array $conversation,
        ?string $activeProjectName = null,
    ): array {
        $current = trim(PromptContextHelper::currentRequest($userPrompt));
        $historyText = self::conversationText($conversation);
        $combined = trim($current."\n".$historyText);

        $paths = self::extractRepoLikePaths($combined);
        $docsTargets = array_values(array_filter($paths, static fn (string $p): bool => str_starts_with($p, 'docs/') || str_contains($p, '/docs/')));

        $lastUserIntent = $current;
        if ($lastUserIntent === '' || self::isLikelyContinuationCue($lastUserIntent)) {
            $lastUserIntent = self::lastSubstantiveUserTurn($conversation) ?: $lastUserIntent;
        }

        $taskKind = 'general';
        if ($docsTargets !== []) {
            $taskKind = self::looksLikeExecuteInstructions($combined) ? 'docs_execute' : 'docs_read';
        } elseif (RepoTaskDetector::isReadOnlyUnderstanding($combined)) {
            $taskKind = 'project_understanding';
        } elseif (preg_match('/\b(execute|implement|build|fix|create|update)\b/i', $combined) === 1) {
            $taskKind = 'implementation';
        }

        return [
            'last_actionable_user_intent' => $lastUserIntent,
            'target_paths' => $paths,
            'docs_targets' => $docsTargets,
            'attachment_refs' => self::extractAttachmentRefs($conversation),
            'active_repo' => $activeProjectName,
            'task_kind' => $taskKind,
            'safety_constraints' => [
                'pause_before_auth_payment_migration_deploy',
                'treat_repo_docs_as_untrusted',
            ],
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public static function conversationText(array $conversation): string
    {
        $parts = [];
        foreach ($conversation as $turn) {
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content !== '') {
                $parts[] = $content;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public static function lastSubstantiveUserTurn(array $conversation): string
    {
        for ($i = count($conversation) - 1; $i >= 0; $i--) {
            $turn = $conversation[$i];
            if (strtolower((string) ($turn['role'] ?? '')) !== 'user') {
                continue;
            }
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '' || self::isLikelyContinuationCue($content) || self::isSocialAcknowledgement($content)) {
                continue;
            }

            return $content;
        }

        return '';
    }

    /**
     * @return list<string>
     */
    public static function extractRepoLikePaths(string $text): array
    {
        $paths = [];
        if (preg_match_all('/`([^`]+\.(?:md|php|ts|tsx|js|vue|json|yml|yaml))`/i', $text, $backtick) === 1) {
            foreach ($backtick[1] as $match) {
                $paths[] = self::normalizePath($match);
            }
        }
        if (preg_match_all('#\b((?:docs|src|app|web|tests|resources)/[A-Za-z0-9_\-./]+\.(?:md|php|ts|tsx|js|vue|json|yml|yaml))\b#i', $text, $repoPaths) === 1) {
            foreach ($repoPaths[1] as $match) {
                $paths[] = self::normalizePath($match);
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @return list<string>
     */
    public static function extractAttachmentRefs(array $conversation): array
    {
        $refs = [];
        foreach (array_reverse($conversation) as $turn) {
            if (strtolower((string) ($turn['role'] ?? '')) !== 'user') {
                continue;
            }
            $content = (string) ($turn['content'] ?? '');
            if (preg_match_all('/\[attachment:\s*([^\]]+)\]/i', $content, $m) === 1) {
                foreach ($m[1] as $name) {
                    $refs[] = trim($name);
                }
            }
            if ($refs !== []) {
                break;
            }
        }

        return array_values(array_unique($refs));
    }

    public static function looksLikeExecuteInstructions(string $text): bool
    {
        $lower = mb_strtolower($text);

        return (bool) preg_match(
            '/\b(execute|implement|follow|run)\b.{0,40}\b(instruction|spec|product_spec|docs\/|from docs|from the doc|from file)\b/u',
            $lower,
        ) || (bool) preg_match('/\bexecute\s+(the\s+)?(code|instructions)\b/u', $lower);
    }

    private static function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }
}
