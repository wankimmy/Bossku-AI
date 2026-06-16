<?php

namespace App\Services\Recipes;

/**
 * A typed, validated recipe parameter (goose recipe parameter parity).
 * input_type: string|number|boolean|date|select|file
 * requirement: required|optional|user_prompt
 */
final class RecipeParameter
{
    public const TYPES = ['string', 'number', 'boolean', 'date', 'select', 'file'];

    public const REQUIREMENTS = ['required', 'optional', 'user_prompt'];

    /** @param list<string> $options */
    public function __construct(
        public readonly string $key,
        public readonly string $inputType = 'string',
        public readonly string $requirement = 'required',
        public readonly string $description = '',
        public readonly ?string $default = null,
        public readonly array $options = [],
    ) {}

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        $type = (string) ($a['input_type'] ?? $a['type'] ?? 'string');
        $req = (string) ($a['requirement'] ?? 'required');

        return new self(
            key: (string) ($a['key'] ?? $a['name'] ?? ''),
            inputType: in_array($type, self::TYPES, true) ? $type : 'string',
            requirement: in_array($req, self::REQUIREMENTS, true) ? $req : 'required',
            description: (string) ($a['description'] ?? ''),
            // File params never carry a default (avoid importing sensitive files).
            default: $type === 'file' ? null : (isset($a['default']) ? (string) $a['default'] : null),
            options: array_values(array_map('strval', (array) ($a['options'] ?? []))),
        );
    }

    public function isRequired(): bool
    {
        return $this->requirement === 'required';
    }

    /**
     * Validate a supplied value (null = not supplied). Returns an error string,
     * or null when valid.
     */
    public function validate(mixed $value): ?string
    {
        $missing = $value === null || $value === '';
        if ($missing) {
            return $this->isRequired() && $this->default === null
                ? "Parameter '{$this->key}' is required."
                : null;
        }

        return match ($this->inputType) {
            'number' => is_numeric($value) ? null : "Parameter '{$this->key}' must be a number.",
            'boolean' => $this->isBoolish($value) ? null : "Parameter '{$this->key}' must be a boolean.",
            'select' => in_array((string) $value, $this->options, true)
                ? null
                : "Parameter '{$this->key}' must be one of: ".implode(', ', $this->options).'.',
            default => null,
        };
    }

    /** Coerce a value to its declared type for templating/output. */
    public function cast(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $this->default;
        }

        return match ($this->inputType) {
            'number' => $value + 0,
            'boolean' => $this->isBoolish($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (bool) $value,
            default => (string) $value,
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'input_type' => $this->inputType,
            'requirement' => $this->requirement,
            'description' => $this->description,
            'default' => $this->default,
            'options' => $this->options,
        ];
    }

    private function isBoolish(mixed $value): bool
    {
        return is_bool($value)
            || in_array(strtolower((string) $value), ['true', 'false', '1', '0', 'yes', 'no'], true);
    }
}
