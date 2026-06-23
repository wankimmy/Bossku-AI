<?php

namespace App\Services\BosskuAi\Learning;

/**
 * Stores and retrieves Instincts with project-scoping. Ported from ECC's
 * continuous-learning v2.1 instinct store. The store keeps instincts in memory
 * (the reference implementation); a database-backed store can be added later
 * by implementing the same interface.
 *
 * Key behaviors:
 * - record(): observe a pattern in a project. If the instinct exists, bump
 *   confidence + sightings; if not, create it at MIN_CONFIDENCE.
 * - forProject(): return instincts scoped to a project (project-scoped + global).
 * - promotionCandidates(): instincts eligible for global promotion.
 * - promote(): promote an instinct to global scope.
 */
final class InstinctStore
{
    /** @var array<string, Instinct> keyed by instinct id */
    private array $instincts = [];

    /**
     * Record an observation. If the instinct already exists (same content +
     * scope), bump its confidence and sightings. If not, create it.
     *
     * @param  string  $content  the observed behavior
     * @param  string  $domain  convention/testing/workflow/security/architecture
     * @param  string  $projectId  git-remote hash of the observing project
     * @param  ?string  $evidence  optional file path or command
     * @return Instinct the created or updated instinct
     */
    public function record(string $content, string $domain, string $projectId, ?string $evidence = null): Instinct
    {
        $scope = 'project:'.$projectId;
        $id = Instinct::idFor($content, $scope);

        if (isset($this->instincts[$id])) {
            $this->instincts[$id]->observe($projectId, $evidence);

            return $this->instincts[$id];
        }

        $instinct = new Instinct(
            id: $id,
            content: $content,
            domain: $domain,
            scope: $scope,
            confidence: Instinct::MIN_CONFIDENCE,
            evidence: $evidence !== null ? [$evidence] : [],
            sightings: 1,
            projectIds: [$projectId],
            createdAt: now()->toIso8601String(),
            updatedAt: now()->toIso8601String(),
        );
        $this->instincts[$id] = $instinct;

        return $instinct;
    }

    /**
     * Get all instincts that apply to a project: project-scoped instincts for
     * that project + all global instincts. Excludes other projects' instincts.
     *
     * @param  string  $projectId
     * @param  ?string  $domain  optional domain filter
     * @return list<Instinct>
     */
    public function forProject(string $projectId, ?string $domain = null): array
    {
        $projectScope = 'project:'.$projectId;

        return array_values(array_filter($this->instincts, function (Instinct $i) use ($projectScope, $domain): bool {
            $scopeMatch = $i->scope === $projectScope || $i->scope === 'global';
            if (! $scopeMatch) {
                return false;
            }
            if ($domain !== null && $i->domain !== $domain) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Instincts eligible for promotion to global: seen in >=2 projects with
     * confidence >=0.8 and still in project scope.
     *
     * @return list<Instinct>
     */
    public function promotionCandidates(): array
    {
        return array_values(array_filter(
            $this->instincts,
            fn (Instinct $i) => $i->isEligibleForPromotion() && str_starts_with($i->scope, 'project:'),
        ));
    }

    /**
     * Promote an instinct to global scope. The original project-scoped instinct
     * is replaced by its global version.
     */
    public function promote(string $instinctId): ?Instinct
    {
        if (! isset($this->instincts[$instinctId])) {
            return null;
        }

        $promoted = $this->instincts[$instinctId]->promote();
        $this->instincts[$instinctId] = $promoted;

        return $promoted;
    }

    public function get(string $id): ?Instinct
    {
        return $this->instincts[$id] ?? null;
    }

    /** @return array<string, Instinct> */
    public function all(): array
    {
        return $this->instincts;
    }

    public function count(): int
    {
        return count($this->instincts);
    }
}