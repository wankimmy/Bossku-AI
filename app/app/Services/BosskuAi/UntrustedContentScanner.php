<?php

namespace App\Services\BosskuAi;

use App\Services\Recipes\RecipeSecurityScanner;

/**
 * Scans repository docs/files treated as instructions for prompt-injection or destructive patterns.
 */
class UntrustedContentScanner
{
    public function __construct(
        private readonly RecipeSecurityScanner $recipeScanner,
    ) {}

    /**
     * @return list<array{severity: string, rule: string, match: string, message: string}>
     */
    public function scan(string $text): array
    {
        return $this->recipeScanner->scanText($text);
    }

    public function hasHighSeverityFindings(string $text): bool
    {
        return $this->recipeScanner->highestSeverity($this->scan($text)) === 'high';
    }

    /**
     * @return list<string>
     */
    public function summarizeBlockedActions(string $text): array
    {
        $blocked = [];
        foreach ($this->scan($text) as $finding) {
            if (($finding['severity'] ?? '') === 'high') {
                $blocked[] = (string) ($finding['message'] ?? $finding['rule'] ?? 'unsafe instruction');
            }
        }

        return array_values(array_unique($blocked));
    }
}
