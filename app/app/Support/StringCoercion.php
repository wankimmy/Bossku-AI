<?php

namespace App\Support;

/**
 * Safe conversion for LLM JSON fields that may be strings or structured arrays.
 */
final class StringCoercion
{
    public static function toString(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $lines = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item === null) {
                    $lines[] = (string) $item;
                } elseif (is_array($item)) {
                    $encoded = json_encode($item, JSON_UNESCAPED_UNICODE);
                    if (is_string($encoded)) {
                        $lines[] = $encoded;
                    }
                }
            }

            if ($lines !== []) {
                return implode("\n", $lines);
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            return is_string($encoded) ? $encoded : $default;
        }

        return $default;
    }
}
