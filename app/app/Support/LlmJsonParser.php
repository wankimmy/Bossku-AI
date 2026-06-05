<?php

namespace App\Support;

/**
 * Best-effort JSON extraction from LLM text (markdown fences, prose wrappers).
 */
final class LlmJsonParser
{
    /**
     * @return array{ok: bool, data: array<string, mixed>|null, error: string|null}
     */
    public static function parseObject(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            return ['ok' => false, 'data' => null, 'error' => 'empty'];
        }

        $text = self::stripBom($text);
        $clean = self::stripMarkdownFences(self::stripThinkingBlocks($text));

        // 1) Prefer the object that begins at the first brace of the cleaned text.
        //    If the model truncated its reply (common when a large prompt leaves little
        //    room in the context window) repair it here so we recover the *outer*
        //    payload — not some smaller nested fragment.
        $outer = self::decodeOuterObject($clean);
        if ($outer !== null) {
            return ['ok' => true, 'data' => $outer, 'error' => null];
        }

        // 2) Fall back to any decodable balanced object found anywhere — handles prose
        //    that contains stray `{...}` braces before the real payload.
        foreach (self::candidateStrings($text) as $candidate) {
            $decoded = self::decode($candidate);
            if (! is_array($decoded)) {
                $cleaned = self::stripTrailingCommas($candidate);
                $decoded = $cleaned !== $candidate ? self::decode($cleaned) : null;
            }
            if (is_array($decoded)) {
                return ['ok' => true, 'data' => $decoded, 'error' => null];
            }
        }

        return ['ok' => false, 'data' => null, 'error' => 'parse'];
    }

    /**
     * Decode the object that starts at the first `{` of $clean: try it balanced, then
     * with trailing commas stripped, then via truncation repair. Returns null when no
     * such object can be recovered (the caller then scans for nested fragments).
     *
     * @return array<string, mixed>|null
     */
    protected static function decodeOuterObject(string $clean): ?array
    {
        $start = strpos($clean, '{');
        if ($start === false) {
            return null;
        }

        $end = self::findBalancedObjectEnd($clean, $start);
        if ($end !== null) {
            $object = substr($clean, $start, $end - $start + 1);
            $decoded = self::decode($object) ?? self::decode(self::stripTrailingCommas($object));
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $repaired = self::repairTruncated($clean);
        if ($repaired !== null) {
            $decoded = self::decode($repaired);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /** @return list<string> */
    protected static function candidateStrings(string $text): array
    {
        $out = [];

        // Strip <think>…</think> / <thinking>…</thinking> before any extraction so that
        // braces inside reasoning tokens don't mislead balanced object scanning.
        $noThink = self::stripThinkingBlocks($text);

        $stripped = self::stripMarkdownFences($noThink);
        if ($stripped !== '') {
            $out[] = $stripped;
        }
        if ($stripped !== $noThink) {
            $out[] = $noThink;
        }

        $sources = array_values(array_unique(array_filter([
            $stripped !== '' ? $stripped : null,
            $noThink !== '' ? $noThink : null,
            $text,
        ])));

        foreach ($sources as $source) {
            foreach (self::extractBalancedObjects($source) as $object) {
                if (! in_array($object, $out, true)) {
                    $out[] = $object;
                }
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Scan for top-level `{…}` slices using brace depth (string-aware).
     *
     * @return list<string> longest-first so the main payload is tried before nested fragments
     */
    protected static function extractBalancedObjects(string $text): array
    {
        $objects = [];
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            $start = strpos($text, '{', $i);
            if ($start === false) {
                break;
            }

            $end = self::findBalancedObjectEnd($text, $start);
            if ($end === null) {
                $i = $start + 1;

                continue;
            }

            $objects[] = substr($text, $start, $end - $start + 1);
            $i = $end + 1;
        }

        usort($objects, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $objects;
    }

    protected static function findBalancedObjectEnd(string $text, int $start): ?int
    {
        if (($text[$start] ?? '') !== '{') {
            return null;
        }

        $len = strlen($text);
        $depth = 0;
        $inString = false;
        $escape = false;

        for ($j = $start; $j < $len; $j++) {
            $c = $text[$j];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($inString) {
                if ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;

                continue;
            }

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return null;
    }

    protected static function stripThinkingBlocks(string $text): string
    {
        // Removes <think>…</think> and <thinking>…</thinking> (case-insensitive, dotall).
        $result = preg_replace('/<think(?:ing)?>\s*.*?\s*<\/think(?:ing)?>/is', '', $text) ?? $text;

        return trim($result);
    }

    protected static function stripBom(string $text): string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            return substr($text, 3);
        }

        return $text;
    }

    protected static function stripMarkdownFences(string $text): string
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```\s*$/', '', $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * Remove commas that directly precede a closing brace/bracket (string-aware).
     * Models frequently leave a trailing comma on the last element, which is valid
     * for them to "think" in but invalid JSON.
     */
    protected static function stripTrailingCommas(string $json): string
    {
        $out = '';
        $len = strlen($json);
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $json[$i];

            if ($inString) {
                $out .= $c;
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;
                $out .= $c;

                continue;
            }

            if ($c === ',') {
                // Look ahead past whitespace for a closing token.
                $j = $i + 1;
                while ($j < $len && ($json[$j] === ' ' || $json[$j] === "\t" || $json[$j] === "\n" || $json[$j] === "\r")) {
                    $j++;
                }
                if ($j < $len && ($json[$j] === '}' || $json[$j] === ']')) {
                    continue; // drop this comma
                }
            }

            $out .= $c;
        }

        return $out;
    }

    /**
     * Best-effort repair of a truncated JSON object: close an open string, drop a
     * dangling trailing comma, supply a null value for a dangling key, then balance
     * any unclosed `{`/`[`. Returns null when the text is not a recoverable object.
     */
    protected static function repairTruncated(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $s = substr($text, $start);
        $len = strlen($s);

        /** @var list<string> $stack */
        $stack = [];
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;
            } elseif ($c === '{' || $c === '[') {
                $stack[] = $c;
            } elseif ($c === '}' || $c === ']') {
                array_pop($stack);
            }
        }

        // Already balanced and not mid-string: the normal decoder handles it.
        if (! $inString && $stack === []) {
            return null;
        }

        $repaired = $s;
        if ($inString) {
            $repaired .= '"';
        }

        $repaired = rtrim($repaired);
        $repaired = preg_replace('/,\s*$/', '', $repaired) ?? $repaired;

        // Inside an object, a trailing quoted token preceded by `{` or `,` is a key with
        // no value yet (the model was cut off). Drop it. A token preceded by `:` is a
        // (closed) value and must be kept.
        if (end($stack) === '{') {
            $repaired = preg_replace('/,\s*"(?:[^"\\\\]|\\\\.)*"\s*$/', '', $repaired)
                ?? $repaired;
            $repaired = preg_replace('/\{\s*"(?:[^"\\\\]|\\\\.)*"\s*$/', '{', $repaired)
                ?? $repaired;
        }

        // Dangling key with a colon but no value yet: "foo":  ->  "foo": null
        if (preg_match('/:\s*$/', $repaired) === 1) {
            $repaired .= 'null';
        }

        for ($k = count($stack) - 1; $k >= 0; $k--) {
            $repaired .= $stack[$k] === '{' ? '}' : ']';
        }

        $repaired = self::stripTrailingCommas($repaired);

        return $repaired;
    }

    /** @return array<string, mixed>|null */
    protected static function decode(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
