<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\RepoTaskDetector;

/**
 * Heuristics for skipping pre-execution clarification when the user prompt is already actionable.
 */
class ClarificationPromptAnalyzer
{
    /**
     * @param  array<string, mixed>  $modelRoute
     */
    public static function isClearEnough(string $prompt, array $modelRoute): bool
    {
        $trimmed = trim($prompt);
        if ($trimmed === '') {
            return false;
        }

        if (RepoTaskDetector::requiresRepositoryAccess($prompt)) {
            return false;
        }

        $workflow = (string) ($modelRoute['workflow'] ?? '');
        if (in_array($workflow, ['direct_answer', 'writer_only'], true)) {
            return true;
        }

        if (preg_match('/^(test|ping|hello|hi|hey)\s*[!?.]*$/i', $trimmed)) {
            return true;
        }

        $lower = mb_strtolower($trimmed);
        if (preg_match('/\b(audit|review|scan|analyse|analyze|inspect)\b/u', $lower)) {
            return false;
        }

        if (preg_match('/\b(what should|not sure|either|or\s+should|help me decide|which approach)\b/u', $lower)) {
            return false;
        }

        if (self::hasConcreteImplementationTarget($trimmed, $lower)) {
            return true;
        }

        return false;
    }

    protected static function hasConcreteImplementationTarget(string $prompt, string $lower): bool
    {
        if (! preg_match('/\b(create|add|write|implement|fix|update|refactor|change|remove|delete)\b/u', $lower)) {
            return false;
        }

        if (preg_match('/\.[a-z0-9]{1,6}\b/i', $prompt)) {
            return true;
        }

        if (preg_match('/\b(routes?\/|app\/|resources\/|src\/|components?\/|controllers?\/)\S+/i', $prompt)) {
            return true;
        }

        if (preg_match('/\b(button|page|view|component|controller|model|migration|endpoint|api)\b/u', $lower)
            && strlen($prompt) >= 24
        ) {
            return true;
        }

        return false;
    }
}
