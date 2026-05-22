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
        $stripped = self::stripMarkdownFences($text);
        if ($stripped !== '') {
            $out[] = $stripped;
        }
        if ($stripped !== $text) {
            $out[] = $text;
        }

        $extracted = self::extractOutermostObject($stripped !== '' ? $stripped : $text);
        if ($extracted !== null && ! in_array($extracted, $out, true)) {
            $out[] = $extracted;
        }

        if ($text !== $stripped && $extracted === null) {
            $fromRaw = self::extractOutermostObject($text);
            if ($fromRaw !== null && ! in_array($fromRaw, $out, true)) {
                $out[] = $fromRaw;
            }
        }

        return array_values(array_unique(array_filter($out)));
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

    protected static function extractOutermostObject(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
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
