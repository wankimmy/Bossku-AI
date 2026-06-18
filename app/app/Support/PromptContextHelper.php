<?php

namespace App\Support;

/**
 * Helpers for multi-turn prompts where routing must use the current user turn only.
 */
class PromptContextHelper
{
    public static function currentRequest(string $prompt): string
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return '';
        }

        if (preg_match('/\nCurrent request:\s*\n(.+)$/si', $prompt, $matches) === 1) {
            return trim((string) $matches[1]);
        }

        return $prompt;
    }

    public static function isMetaAboutAssistant(string $prompt): bool
    {
        $current = mb_strtolower(self::currentRequest($prompt));
        $trim = trim($current);

        if (preg_match(
            '/\b(what are you good at|what can you do|what do you do|who are you|about yourself|your capabilities|what are your (skills|strengths|capabilities)|tell me about yourself|what can bossku|how can you help|what are you capable of|what do you specialize in|what are you best at)\b/u',
            $current,
        ) === 1) {
            return true;
        }

        return preg_match('/^(what are you|who are you|what can you|how can you help)\b/u', $trim) === 1;
    }
}
