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
        $candidates = self::candidateStrings($text);

        foreach ($candidates as $candidate) {
            $decoded = self::decode($candidate);
            if (is_array($decoded)) {
                return ['ok' => true, 'data' => $decoded, 'error' => null];
            }
        }

        return ['ok' => false, 'data' => null, 'error' => 'parse'];
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
