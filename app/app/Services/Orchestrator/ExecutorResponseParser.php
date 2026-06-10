<?php

namespace App\Services\Orchestrator;

use App\Support\StringCoercion;

/**
 * Normalize and validate executor LLM JSON before strict pipeline use.
 */
final class ExecutorResponseParser
{
    /**
     * Full-line placeholder markers that mean the model elided real file content.
     * Kept conservative so legitimate code (e.g. PHP spread `...$args`) never matches.
     *
     * @var list<string>
     */
    private const PLACEHOLDER_PATTERNS = [
        '/^[+\s]*(?:\/\/|#|\/\*)\s*(?:\.\.\.|…)\s*(?:\*\/)?\s*$/m',
        '/rest of (?:the )?file (?:is |remains )?unchanged/i',
        '/existing code (?:unchanged|omitted|remains|here)/i',
        '/omitted for brevity/i',
        '/unchanged lines? (?:omitted|skipped)/i',
    ];

    /**
     * Gate for ModelFallbackService (before normalization).
     *
     * Default mode is the historical relaxed check. Strict mode (opt-in via the
     * `executor_strict_validation` setting) additionally rejects responses that
     * claim success without carrying real change content, so hallucinated
     * completions trigger an immediate retry/fallback instead of burning an
     * audit round to be caught.
     *
     * @param  array<string, mixed>  $decoded
     * @param  bool  $expectsChanges  the plan targets files on a write-intent task
     */
    public static function validateForFallback(array $decoded, bool $strict = false, bool $expectsChanges = false): bool
    {
        if ($decoded === []) {
            return false;
        }

        $relaxedOk = isset($decoded['status']) || isset($decoded['patch_summary']) || isset($decoded['summary'])
            || (isset($decoded['message']) && StringCoercion::toString($decoded['message']) !== '');

        if (! $strict || ! $relaxedOk) {
            return $relaxedOk;
        }

        $status = StringCoercion::toString($decoded['status'] ?? null, '');
        $filesChanged = is_array($decoded['files_changed'] ?? null) ? $decoded['files_changed'] : [];
        $commandsRun = is_array($decoded['commands_run'] ?? null) ? $decoded['commands_run'] : [];

        if ($status === 'success') {
            foreach ($filesChanged as $item) {
                if (! self::changeCarriesContent($item)) {
                    return false;
                }
            }

            // A write-intent task that "succeeded" while touching nothing is the
            // classic hallucinated completion. Partial/failed/blocked responses
            // are exempt — they already route to revision or user escalation.
            if ($expectsChanges
                && $filesChanged === []
                && $commandsRun === []
                && ($decoded['needs_user_input'] ?? false) !== true) {
                return false;
            }
        }

        return ! self::containsPlaceholderContent($filesChanged);
    }

    /**
     * A claimed file change must carry applyable content: a diff or full `after`
     * contents for modify/create, nothing required for deletes.
     */
    private static function changeCarriesContent(mixed $item): bool
    {
        if (is_string($item)) {
            return false;
        }
        if (! is_array($item)) {
            return false;
        }

        $changeType = StringCoercion::toString($item['change_type'] ?? null, 'modified');
        if ($changeType === 'deleted') {
            return true;
        }

        $after = StringCoercion::toString($item['after'] ?? $item['new_contents'] ?? $item['contents'] ?? null, '');
        $diff = StringCoercion::toString($item['diff'] ?? null, '');

        return $after !== '' || $diff !== '';
    }

    /** True when file content carries an elision marker instead of real code. */
    public static function contentHasPlaceholders(string $content): bool
    {
        if (trim($content) === '') {
            return false;
        }

        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<mixed>  $filesChanged */
    private static function containsPlaceholderContent(array $filesChanged): bool
    {
        foreach ($filesChanged as $item) {
            if (! is_array($item)) {
                continue;
            }
            $content = StringCoercion::toString($item['after'] ?? null, '')
                ."\n".StringCoercion::toString($item['diff'] ?? null, '');
            if (self::contentHasPlaceholders($content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    public static function normalize(array $decoded): array
    {
        $patch = StringCoercion::toString(
            $decoded['patch_summary'] ?? $decoded['summary'] ?? $decoded['message'] ?? null,
            '',
        );

        $status = StringCoercion::toString($decoded['status'] ?? null, '');
        if ($status === '') {
            $status = $patch !== '' ? 'success' : 'partial';
        }

        return array_merge($decoded, [
            'status' => $status,
            'patch_summary' => $patch,
            'handoff_message' => StringCoercion::toString(
                $decoded['handoff_message'] ?? null,
                'Sending changes to Auditor.',
            ),
            'files_read' => is_array($decoded['files_read'] ?? null) ? $decoded['files_read'] : [],
            'files_changed' => is_array($decoded['files_changed'] ?? null) ? $decoded['files_changed'] : [],
            'commands_run' => is_array($decoded['commands_run'] ?? null) ? $decoded['commands_run'] : [],
            'tests_run' => is_array($decoded['tests_run'] ?? null) ? $decoded['tests_run'] : [],
            'tests_result' => StringCoercion::toString($decoded['tests_result'] ?? null, 'not_run'),
            'known_issues' => is_array($decoded['known_issues'] ?? null) ? $decoded['known_issues'] : [],
            'blockers' => is_array($decoded['blockers'] ?? null) ? $decoded['blockers'] : [],
            'suggested_options' => is_array($decoded['suggested_options'] ?? null) ? $decoded['suggested_options'] : [],
            'needs_user_input' => (bool) ($decoded['needs_user_input'] ?? false),
            'questions' => is_array($decoded['questions'] ?? null) ? $decoded['questions'] : [],
            'needs_audit' => (bool) ($decoded['needs_audit'] ?? true),
        ]);
    }
}
