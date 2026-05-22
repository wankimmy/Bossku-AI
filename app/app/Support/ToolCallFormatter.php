<?php

namespace App\Support;

/**
 * Human-readable labels for BosskuAI tool invocations (SSE + logs).
 */
final class ToolCallFormatter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function summary(string $tool, array $payload, string $status = 'ok'): string
    {
        $detail = self::actionDetail($tool, $payload);
        $suffix = match ($status) {
            'error' => ' (failed)',
            'blocked' => ' (blocked)',
            default => '',
        };

        return $detail.$suffix;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function actionDetail(string $tool, array $payload): string
    {
        return match ($tool) {
            'file_read_safe' => 'Reading file: '.self::path($payload),
            'file_search' => 'Searching repo for "'.self::query($payload).'"'
                .(self::glob($payload) !== '*' ? ' in '.self::glob($payload) : ''),
            'file_glob' => 'Listing files: '.self::pattern($payload),
            'file_write_proposed' => 'Proposing file write: '.self::path($payload),
            'db_query' => 'Running read-only SQL: '.self::sqlPreview($payload),
            'log' => 'Log: '.self::truncate(self::string($payload['message'] ?? ''), 120),
            default => 'Tool: '.$tool,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadPreview(string $tool, array $payload): array
    {
        return match ($tool) {
            'file_read_safe' => ['path' => self::path($payload)],
            'file_search' => ['q' => self::query($payload), 'glob' => self::glob($payload)],
            'file_glob' => ['pattern' => self::pattern($payload)],
            'file_write_proposed' => ['path' => self::path($payload)],
            'db_query' => ['sql' => self::sqlPreview($payload)],
            'log' => ['message' => self::truncate(self::string($payload['message'] ?? ''), 200)],
            default => $payload,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function path(array $payload): string
    {
        $path = self::string($payload['path'] ?? '');

        return $path !== '' ? $path : '(path missing)';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function query(array $payload): string
    {
        return self::truncate(self::string($payload['q'] ?? $payload['query'] ?? ''), 80) ?: '(empty)';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function glob(array $payload): string
    {
        return self::string($payload['glob'] ?? '*') ?: '*';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function pattern(array $payload): string
    {
        return self::truncate(self::string($payload['pattern'] ?? $payload['glob'] ?? ''), 120) ?: '(pattern missing)';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function sqlPreview(array $payload): string
    {
        $sql = preg_replace('/\s+/', ' ', self::string($payload['sql'] ?? '')) ?? '';

        return self::truncate($sql, 160) ?: '(empty query)';
    }

    protected static function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return StringCoercion::toString($value);
        }

        return '';
    }

    protected static function truncate(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max - 1).'…';
    }
}
