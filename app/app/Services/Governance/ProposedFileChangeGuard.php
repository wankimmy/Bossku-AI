<?php

namespace App\Services\Governance;

class ProposedFileChangeGuard
{
    /**
     * @return string|null Human-readable rejection reason, or null if OK to propose/apply.
     */
    public function validate(string $before, string $after, string $changeType, ?string $relativePath = null): ?string
    {
        $changeType = strtolower(trim($changeType));

        if ($changeType === 'deleted') {
            return null;
        }

        if ($changeType === 'created') {
            if (trim($after) === '') {
                return 'New file proposal has empty content.';
            }
            if ($this->isPlaceholderText($after)) {
                return 'New file content looks like a placeholder, not real source code.';
            }

            return null;
        }

        if (trim($after) === '') {
            return 'Modified file proposal has empty after content.';
        }

        if ($this->isPlaceholderText($after)) {
            return 'Proposed file content is placeholder text, not complete file contents.';
        }

        if ($this->isDestructiveWipe($before, $after, $changeType)) {
            return 'Proposed change would replace almost the entire file — executor must supply full file contents or a valid diff.';
        }

        if ($this->isMissingPhpStructure($before, $after, $relativePath, $changeType)) {
            return 'Proposed PHP file content is missing expected structure (<?php, class, or function).';
        }

        return null;
    }

    public function isPlaceholderText(string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return true;
        }

        if (preg_match('/^(?:\.{3}|…)$/u', $trimmed)) {
            return true;
        }

        $lower = strtolower($trimmed);
        $isShortProposal = strlen($trimmed) <= 500;
        $patterns = [
            'will be determined',
            'to be determined',
            'tbd',
            'todo',
            'placeholder',
            'to be filled',
            'read the file',
            'after reading the file',
            'not yet written',
            'coming soon',
            'fill in later',
        ];

        foreach ($patterns as $pattern) {
            if ($lower === $pattern || ($isShortProposal && str_contains($lower, $pattern))) {
                return true;
            }
        }

        return false;
    }

    public function isDestructiveWipe(string $before, string $after, string $changeType): bool
    {
        if ($changeType !== 'modified' || $before === '') {
            return false;
        }

        $beforeLines = $this->lineCount($before);
        $afterLines = $this->lineCount($after);

        if ($beforeLines >= 20 && $afterLines <= 2) {
            return true;
        }

        $beforeLen = strlen($before);
        $afterLen = strlen($after);

        if ($beforeLen >= 200 && $afterLen > 0 && $afterLen < (int) ($beforeLen * 0.15)) {
            return true;
        }

        return false;
    }

    public function isMissingPhpStructure(
        string $before,
        string $after,
        ?string $relativePath,
        string $changeType,
    ): bool {
        if ($changeType !== 'modified') {
            return false;
        }

        $path = strtolower((string) $relativePath);
        if ($path !== '' && ! str_ends_with($path, '.php')) {
            return false;
        }

        $beforeLooksPhp = str_contains($before, '<?php')
            || preg_match('/\b(class|function)\b/', $before);
        if (! $beforeLooksPhp) {
            return false;
        }

        return ! str_contains($after, '<?php')
            && ! preg_match('/\b(class|function)\b/', $after);
    }

    private function lineCount(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return substr_count($text, "\n") + (str_ends_with($text, "\n") ? 0 : 1);
    }
}
