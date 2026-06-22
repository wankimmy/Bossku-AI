<?php

namespace App\Services\Project;

/**
 * Surgical string-replacement engine for code edits.
 *
 * PHP port of opencode's multi-strategy edit replacer cascade
 * (packages/opencode/src/tool/edit.ts), itself sourced from cline and
 * gemini-cli. Instead of asking the model to reproduce a whole file or emit a
 * line-numbered unified diff, the model quotes the exact snippet to change
 * (oldString) and the replacement (newString). A cascade of replacers locates
 * the snippet even when whitespace, indentation, or escaping drift from what
 * the model remembered — the property that makes surgical edits land reliably.
 *
 * Strategy order (first match wins): exact → line-trimmed → block-anchor →
 * whitespace-normalized → indentation-flexible → escape-normalized →
 * multi-occurrence.
 */
class FileEditEngine
{
    /** Block-anchor similarity floor for accepting a fuzzy middle match. */
    private const ANCHOR_SIMILARITY_THRESHOLD = 0.65;

    /**
     * Apply a single replacement and return the new content.
     *
     * @throws EditNotFoundException     when oldString cannot be located
     * @throws EditAmbiguousException    when oldString matches multiple spans (and replaceAll is false)
     * @throws \InvalidArgumentException when the edit is degenerate
     */
    public function replace(string $content, string $oldString, string $newString, bool $replaceAll = false): string
    {
        if ($oldString === $newString) {
            throw new \InvalidArgumentException('No changes to apply: oldString and newString are identical.');
        }
        if ($oldString === '') {
            throw new \InvalidArgumentException('oldString cannot be empty. Provide the exact text to replace, or write the full file.');
        }

        $notFound = true;

        foreach ($this->replacers() as $replacer) {
            foreach ($replacer($content, $oldString) as $search) {
                if ($search === '') {
                    continue;
                }
                $index = strpos($content, $search);
                if ($index === false) {
                    continue;
                }
                $notFound = false;

                if ($this->isDisproportionateMatch($search, $oldString)) {
                    throw new \InvalidArgumentException('Refusing replacement: matched span is much larger than oldString. Provide the full exact oldString.');
                }

                if ($replaceAll) {
                    return str_replace($search, $newString, $content);
                }

                if ($index !== strrpos($content, $search)) {
                    // Ambiguous under this replacer; let later replacers try a
                    // more specific match before giving up.
                    continue;
                }

                return substr($content, 0, $index).$newString.substr($content, $index + strlen($search));
            }
        }

        if ($notFound) {
            throw new EditNotFoundException('Could not find oldString in the file. It must match the file content (whitespace and indentation may differ).');
        }

        throw new EditAmbiguousException('Found multiple matches for oldString. Provide more surrounding context to make the match unique, or set replace_all.');
    }

    /**
     * Apply a sequence of edits to $content, in order.
     *
     * @param  list<array{old_string?: string, oldString?: string, new_string?: string, newString?: string, replace_all?: bool, replaceAll?: bool}>  $edits
     */
    public function applyEdits(string $content, array $edits): string
    {
        foreach ($edits as $edit) {
            if (! is_array($edit)) {
                continue;
            }
            $old = (string) ($edit['old_string'] ?? $edit['oldString'] ?? '');
            $new = (string) ($edit['new_string'] ?? $edit['newString'] ?? '');
            $replaceAll = (bool) ($edit['replace_all'] ?? $edit['replaceAll'] ?? false);
            $content = $this->replace($content, $old, $new, $replaceAll);
        }

        return $content;
    }

    /** @return list<callable(string, string): \Generator<int, string>> */
    private function replacers(): array
    {
        return [
            [$this, 'simple'],
            [$this, 'lineTrimmed'],
            [$this, 'blockAnchor'],
            [$this, 'whitespaceNormalized'],
            [$this, 'indentationFlexible'],
            [$this, 'escapeNormalized'],
            [$this, 'multiOccurrence'],
        ];
    }

    /** @return \Generator<int, string> */
    public function simple(string $content, string $find): \Generator
    {
        yield $find;
    }

    /**
     * Match line-by-line ignoring leading/trailing whitespace per line, then
     * yield the original (untrimmed) span so the replacement preserves layout.
     *
     * @return \Generator<int, string>
     */
    public function lineTrimmed(string $content, string $find): \Generator
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);
        if (end($searchLines) === '') {
            array_pop($searchLines);
        }
        $searchLines = array_values($searchLines);
        $searchCount = count($searchLines);
        if ($searchCount === 0) {
            return;
        }

        $originalCount = count($originalLines);
        for ($i = 0; $i <= $originalCount - $searchCount; $i++) {
            $matches = true;
            for ($j = 0; $j < $searchCount; $j++) {
                if (trim($originalLines[$i + $j]) !== trim($searchLines[$j])) {
                    $matches = false;
                    break;
                }
            }
            if (! $matches) {
                continue;
            }

            $start = 0;
            for ($k = 0; $k < $i; $k++) {
                $start += strlen($originalLines[$k]) + 1;
            }
            $end = $start;
            for ($k = 0; $k < $searchCount; $k++) {
                $end += strlen($originalLines[$i + $k]);
                if ($k < $searchCount - 1) {
                    $end += 1;
                }
            }

            yield substr($content, $start, $end - $start);
        }
    }

    /**
     * Anchor on the first and last line of a >=3 line block, then accept the
     * span if its middle lines are similar enough (Levenshtein) to the search.
     * Handles cases where interior lines drifted but the boundaries are intact.
     *
     * @return \Generator<int, string>
     */
    public function blockAnchor(string $content, string $find): \Generator
    {
        $originalLines = explode("\n", $content);
        $searchLines = explode("\n", $find);
        if (count($searchLines) < 3) {
            return;
        }
        if (end($searchLines) === '') {
            array_pop($searchLines);
        }
        $searchLines = array_values($searchLines);
        $blockSize = count($searchLines);

        $firstSearch = trim($searchLines[0]);
        $lastSearch = trim($searchLines[$blockSize - 1]);
        $maxLineDelta = max(1, (int) floor($blockSize * 0.25));

        $candidates = [];
        $originalCount = count($originalLines);
        for ($i = 0; $i < $originalCount; $i++) {
            if (trim($originalLines[$i]) !== $firstSearch) {
                continue;
            }
            for ($j = $i + 2; $j < $originalCount; $j++) {
                if (trim($originalLines[$j]) === $lastSearch) {
                    if (abs(($j - $i + 1) - $blockSize) <= $maxLineDelta) {
                        $candidates[] = [$i, $j];
                    }
                    break;
                }
            }
        }

        if ($candidates === []) {
            return;
        }

        foreach ($candidates as [$startLine, $endLine]) {
            $actualBlockSize = $endLine - $startLine + 1;
            $similarity = $this->middleSimilarity($originalLines, $searchLines, $startLine, $blockSize, $actualBlockSize);
            if ($similarity < self::ANCHOR_SIMILARITY_THRESHOLD) {
                continue;
            }

            $start = 0;
            for ($k = 0; $k < $startLine; $k++) {
                $start += strlen($originalLines[$k]) + 1;
            }
            $end = $start;
            for ($k = $startLine; $k <= $endLine; $k++) {
                $end += strlen($originalLines[$k]);
                if ($k < $endLine) {
                    $end += 1;
                }
            }

            yield substr($content, $start, $end - $start);
        }
    }

    /** @param list<string> $originalLines @param list<string> $searchLines */
    private function middleSimilarity(array $originalLines, array $searchLines, int $startLine, int $searchBlockSize, int $actualBlockSize): float
    {
        $linesToCheck = min($searchBlockSize - 2, $actualBlockSize - 2);
        if ($linesToCheck <= 0) {
            return 1.0;
        }

        $similarity = 0.0;
        for ($j = 1; $j < $searchBlockSize - 1 && $j < $actualBlockSize - 1; $j++) {
            $original = trim($originalLines[$startLine + $j]);
            $search = trim($searchLines[$j]);
            $maxLen = max(strlen($original), strlen($search));
            if ($maxLen === 0) {
                continue;
            }
            $distance = $this->levenshtein($original, $search);
            $similarity += (1 - $distance / $maxLen) / $linesToCheck;
        }

        return $similarity;
    }

    /**
     * Collapse runs of whitespace to a single space before comparing, so edits
     * survive reflowed indentation and trailing whitespace.
     *
     * @return \Generator<int, string>
     */
    public function whitespaceNormalized(string $content, string $find): \Generator
    {
        $normalize = static fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', $text));
        $normalizedFind = $normalize($find);
        if ($normalizedFind === '') {
            return;
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            if ($normalize($line) === $normalizedFind) {
                yield $line;

                continue;
            }
            if (! str_contains($normalize($line), $normalizedFind)) {
                continue;
            }
            $words = preg_split('/\s+/', trim($find)) ?: [];
            if ($words === []) {
                continue;
            }
            $pattern = '/'.implode('\s+', array_map(static fn (string $w) => preg_quote($w, '/'), $words)).'/';
            if (@preg_match($pattern, $line, $m) === 1) {
                yield $m[0];
            }
        }

        $findLines = explode("\n", $find);
        $findCount = count($findLines);
        if ($findCount <= 1) {
            return;
        }
        $lineCount = count($lines);
        for ($i = 0; $i <= $lineCount - $findCount; $i++) {
            $block = implode("\n", array_slice($lines, $i, $findCount));
            if ($normalize($block) === $normalizedFind) {
                yield $block;
            }
        }
    }

    /**
     * Strip the common minimum indentation before comparing, so a block pasted
     * at a different nesting depth still matches.
     *
     * @return \Generator<int, string>
     */
    public function indentationFlexible(string $content, string $find): \Generator
    {
        $normalizedFind = $this->removeIndentation($find);
        $contentLines = explode("\n", $content);
        $findLines = explode("\n", $find);
        $findCount = count($findLines);
        $contentCount = count($contentLines);

        for ($i = 0; $i <= $contentCount - $findCount; $i++) {
            $block = implode("\n", array_slice($contentLines, $i, $findCount));
            if ($this->removeIndentation($block) === $normalizedFind) {
                yield $block;
            }
        }
    }

    private function removeIndentation(string $text): string
    {
        $lines = explode("\n", $text);
        $nonEmpty = array_filter($lines, static fn (string $l) => trim($l) !== '');
        if ($nonEmpty === []) {
            return $text;
        }
        $minIndent = PHP_INT_MAX;
        foreach ($nonEmpty as $line) {
            preg_match('/^(\s*)/', $line, $m);
            $minIndent = min($minIndent, strlen($m[1] ?? ''));
        }
        if ($minIndent === PHP_INT_MAX || $minIndent === 0) {
            return implode("\n", $lines);
        }

        return implode("\n", array_map(
            static fn (string $line) => trim($line) === '' ? $line : substr($line, $minIndent),
            $lines,
        ));
    }

    /**
     * Match content where the model double-escaped sequences like \n or \t.
     *
     * @return \Generator<int, string>
     */
    public function escapeNormalized(string $content, string $find): \Generator
    {
        $unescape = static fn (string $str): string => (string) preg_replace_callback(
            '/\\\\(n|t|r|\'|"|`|\\\\|\n|\$)/',
            static function (array $m): string {
                return match ($m[1]) {
                    'n', "\n" => "\n",
                    't' => "\t",
                    'r' => "\r",
                    "'" => "'",
                    '"' => '"',
                    '`' => '`',
                    '\\' => '\\',
                    '$' => '$',
                    default => $m[0],
                };
            },
            $str,
        );

        $unescapedFind = $unescape($find);
        if ($unescapedFind !== '' && str_contains($content, $unescapedFind)) {
            yield $unescapedFind;
        }

        $lines = explode("\n", $content);
        $findLines = explode("\n", $unescapedFind);
        $findCount = count($findLines);
        $lineCount = count($lines);
        for ($i = 0; $i <= $lineCount - $findCount; $i++) {
            $block = implode("\n", array_slice($lines, $i, $findCount));
            if ($unescape($block) === $unescapedFind) {
                yield $block;
            }
        }
    }

    /** @return \Generator<int, string> */
    public function multiOccurrence(string $content, string $find): \Generator
    {
        $offset = 0;
        while (true) {
            $index = strpos($content, $find, $offset);
            if ($index === false) {
                break;
            }
            yield $find;
            $offset = $index + strlen($find);
        }
    }

    /**
     * Reject a fuzzy match that ballooned far beyond the requested oldString —
     * a strong signal the replacer locked onto the wrong span.
     */
    private function isDisproportionateMatch(string $search, string $oldString): bool
    {
        $oldLines = substr_count($oldString, "\n") + 1;
        $searchLines = substr_count($search, "\n") + 1;
        if ($searchLines >= max($oldLines + 3, $oldLines * 2)) {
            return true;
        }
        if ($oldLines === 1) {
            return false;
        }

        return strlen(trim($search)) > max(strlen(trim($oldString)) + 500, strlen(trim($oldString)) * 4);
    }

    /** Levenshtein distance without PHP's 255-byte ceiling. */
    private function levenshtein(string $a, string $b): int
    {
        if ($a === '' || $b === '') {
            return max(strlen($a), strlen($b));
        }
        if (strlen($a) < 256 && strlen($b) < 256) {
            return \levenshtein($a, $b);
        }

        $la = strlen($a);
        $lb = strlen($b);
        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $curr = [$i];
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $curr[$j] = min($prev[$j] + 1, $curr[$j - 1] + 1, $prev[$j - 1] + $cost);
            }
            $prev = $curr;
        }

        return $prev[$lb];
    }
}
