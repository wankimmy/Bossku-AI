<?php

namespace App\Services\BosskuAi;

/**
 * Picks the strongest model for a role given the *domain* of the change, instead
 * of always leading with a single hard-fixed model. Inspired by Sakana Fugu's
 * finding that a fixed aggregator/reviewer is a bottleneck: the best synthesizer
 * for a math-heavy change is not the best one for a UI change.
 *
 * It only reorders an already-resolved candidate list (primary + fallbacks) by
 * capability fit, using the existing `bossku_inference_catalog` scores. The set of
 * candidates never changes, so fallback behavior is preserved — only the order in
 * which they are tried. Unknown models keep their relative position at the tail.
 */
class DomainModelSelector
{
    /**
     * Per-domain weighting over the catalog axes (reasoning, coding, speed, cost).
     * Higher cost = more cost-efficient, matching the catalog convention.
     *
     * @var array<string, array{reasoning: float, coding: float, speed: float, cost: float}>
     */
    private const DOMAIN_WEIGHTS = [
        'security' => ['reasoning' => 0.60, 'coding' => 0.30, 'speed' => 0.00, 'cost' => 0.10],
        'reasoning' => ['reasoning' => 0.70, 'coding' => 0.20, 'speed' => 0.00, 'cost' => 0.10],
        'data' => ['reasoning' => 0.55, 'coding' => 0.35, 'speed' => 0.00, 'cost' => 0.10],
        'backend' => ['reasoning' => 0.45, 'coding' => 0.45, 'speed' => 0.00, 'cost' => 0.10],
        'devops' => ['reasoning' => 0.30, 'coding' => 0.50, 'speed' => 0.10, 'cost' => 0.10],
        'frontend' => ['reasoning' => 0.25, 'coding' => 0.60, 'speed' => 0.05, 'cost' => 0.10],
        'general' => ['reasoning' => 0.45, 'coding' => 0.35, 'speed' => 0.10, 'cost' => 0.10],
    ];

    /**
     * Derive a coarse domain from the routing decision the orchestrator already made.
     * Uses the executor profile and skill tag first, then falls back to a keyword scan
     * of the route so it degrades gracefully if the shape changes.
     *
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $router
     */
    public function domainFor(array $modelRoute, array $router = []): string
    {
        $haystack = strtolower(json_encode([$modelRoute, $router]) ?: '');

        // Security/abuse signals win regardless of profile.
        if ($this->matchesAny($haystack, [
            'security', 'auth', 'payment', 'billing', 'secret', 'token', 'password',
            'permission', 'webhook', 'owasp', 'vulnerab',
        ])) {
            return 'security';
        }

        $profile = (string) ($modelRoute['executor_profile'] ?? '');
        $byProfile = match ($profile) {
            'frontend_ui' => 'frontend',
            'backend' => 'backend',
            'devops' => 'devops',
            'high_risk' => 'security',
            default => null,
        };
        if ($byProfile !== null) {
            return $byProfile;
        }

        if ($this->matchesAny($haystack, ['migration', 'database', 'schema', 'query', 'analytics', 'pipeline'])) {
            return 'data';
        }
        if ($this->matchesAny($haystack, ['frontend', 'ui', 'ux', 'design', 'css', 'tailwind', 'component', 'vue', 'react'])) {
            return 'frontend';
        }
        if ($this->matchesAny($haystack, ['docker', 'deploy', 'ci/cd', 'ci ', 'infra', 'devops', 'pipeline'])) {
            return 'devops';
        }
        if ($this->matchesAny($haystack, ['api', 'backend', 'laravel', 'service', 'controller', 'endpoint'])) {
            return 'backend';
        }

        return 'general';
    }

    /**
     * Reorder a candidate list so the best domain-fit model leads.
     *
     * @param  list<string>  $candidates  logical model ids, primary first
     * @param  bool  $keepPrimary  when the role's model was explicitly pinned by the
     *                             user, keep it first and only reorder the fallbacks
     * @return list<string>
     */
    public function order(array $candidates, string $domain, bool $keepPrimary = false): array
    {
        $candidates = array_values(array_unique(array_filter($candidates, static fn ($c) => is_string($c) && $c !== '')));
        if (count($candidates) < 2) {
            return $candidates;
        }
        if (! (bool) config('bossku_models.domain_adaptive_reviewers', true)) {
            return $candidates;
        }

        $pinned = [];
        if ($keepPrimary) {
            $pinned = [array_shift($candidates)];
        }

        $weights = self::DOMAIN_WEIGHTS[$domain] ?? self::DOMAIN_WEIGHTS['general'];

        // Stable sort by descending domain score; preserve original order on ties.
        $indexed = [];
        foreach ($candidates as $i => $id) {
            $indexed[] = ['id' => $id, 'i' => $i, 'score' => $this->scoreModel($id, $weights)];
        }
        usort($indexed, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'] ?: $a['i'] <=> $b['i'];
        });

        return array_values([...$pinned, ...array_map(static fn (array $r): string => $r['id'], $indexed)]);
    }

    /**
     * Reorder candidates for a revision/retry so a model from a *different* family than
     * the one that just failed leads — Fugu's build-then-debug alternation, where bringing
     * in a complementary model beats retrying the same one. Domain fit breaks ties.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    public function complementTo(string $justUsedModel, array $candidates, string $domain): array
    {
        $ordered = $this->order($candidates, $domain, false);
        $usedFamily = $this->family($justUsedModel);

        $different = array_values(array_filter($ordered, fn ($id) => $this->family($id) !== $usedFamily));
        $same = array_values(array_filter($ordered, fn ($id) => $this->family($id) === $usedFamily));

        return array_values([...$different, ...$same]);
    }

    /**
     * @param  array{reasoning: float, coding: float, speed: float, cost: float}  $weights
     */
    private function scoreModel(string $logicalId, array $weights): float
    {
        $model = $this->catalogRow($logicalId);
        if ($model === null) {
            return 0.0;
        }

        return (float) ($model['reasoning'] ?? 0) * $weights['reasoning']
            + (float) ($model['coding'] ?? 0) * $weights['coding']
            + (float) ($model['speed'] ?? 0) * $weights['speed']
            + (float) ($model['cost'] ?? 0) * $weights['cost'];
    }

    /** @return array<string, mixed>|null */
    private function catalogRow(string $logicalId): ?array
    {
        $needle = $this->normalize($logicalId);
        /** @var list<array<string, mixed>> $models */
        $models = config('bossku_inference_catalog.models', []);
        foreach ($models as $model) {
            if (($model['available'] ?? true) === false) {
                continue;
            }
            if ($this->normalize((string) ($model['id'] ?? '')) === $needle) {
                return $model;
            }
        }

        return null;
    }

    /** Provider/version-agnostic family key, e.g. "deepseek-v4-pro:cloud" and "deepseek-v4-pro" → "deepseek". */
    private function family(string $logicalId): string
    {
        $id = $this->normalize($logicalId);
        $prefix = explode('-', $id)[0];

        return $prefix !== '' ? $prefix : $id;
    }

    private function normalize(string $id): string
    {
        $id = strtolower(trim($id));

        return str_ends_with($id, ':cloud') ? substr($id, 0, -6) : $id;
    }

    /** @param list<string> $needles */
    private function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
