<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * A typed, namespaced key for a System Context source. Branded so duplicate
 * keys are caught at the type level. Ported from opencode's Source key model.
 *
 * Keys are dotted namespaced strings: "bossku/instructions/global",
 * "bossku/memory/durable", "bossku/skill/available", "bossku/persona/executor",
 * "bossku/date". The namespace prefix groups related sources for ordering and
 * for partial replacement during compaction.
 */
final readonly class ContextKey
{
    public function __construct(public string $value)
    {
        if ($value === '' || ! str_contains($value, '/')) {
            throw new \InvalidArgumentException("ContextKey must be a namespaced dotted string (e.g. 'bossku/instructions/global'), got: {$value}");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function namespace(): string
    {
        return explode('/', $this->value, 2)[0];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}