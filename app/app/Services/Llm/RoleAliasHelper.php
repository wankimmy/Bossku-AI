<?php

namespace App\Services\Llm;

class RoleAliasHelper
{
    /** @var array<string, string> */
    protected static array $aliases = [
        'planner' => 'orchestrator',
        'coder' => 'executor',
        'reviewer' => 'final_reviewer',
    ];

    public static function normalize(string $role): string
    {
        $role = strtolower(trim($role));

        return self::$aliases[$role] ?? $role;
    }

    /** @return list<string> */
    public static function variants(string $role): array
    {
        $normalized = self::normalize($role);
        $variants = [$normalized, $role];

        foreach (self::$aliases as $alias => $canonical) {
            if ($canonical === $normalized) {
                $variants[] = $alias;
            }
        }

        return array_values(array_unique($variants));
    }
}
