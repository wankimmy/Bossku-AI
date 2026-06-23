<?php

namespace Tests\Unit;

use App\Services\BosskuAi\Ralphinho\WorkUnit;
use App\Services\BosskuAi\Ralphinho\WorkUnitTier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the Ralphinho RFC-driven DAG primitives. Proves: WorkUnit
 * dependency blocking, tier-driven pipeline depth, the classification
 * heuristic, and separate-context-window stage lists.
 */
class RalphinhoDagTest extends TestCase
{
    #[Test]
    public function trivial_tier_has_one_stage(): void
    {
        $this->assertSame(1, WorkUnitTier::Trivial->pipelineStages());
        $this->assertSame(['plan', 'execute'], WorkUnitTier::Trivial->stages());
    }

    #[Test]
    public function small_tier_has_three_stages(): void
    {
        $this->assertSame(3, WorkUnitTier::Small->pipelineStages());
        $this->assertSame(['plan', 'execute', 'review'], WorkUnitTier::Small->stages());
    }

    #[Test]
    public function medium_tier_has_five_stages(): void
    {
        $this->assertSame(5, WorkUnitTier::Medium->pipelineStages());
        $this->assertSame(['plan', 'execute', 'test', 'review', 'refactor'], WorkUnitTier::Medium->stages());
    }

    #[Test]
    public function large_tier_has_seven_stages(): void
    {
        $this->assertSame(7, WorkUnitTier::Large->pipelineStages());
        $this->assertSame(['plan', 'design', 'execute', 'test', 'review', 'security-audit', 'refactor'], WorkUnitTier::Large->stages());
    }

    #[Test]
    public function classify_trivial(): void
    {
        $this->assertSame(WorkUnitTier::Trivial, WorkUnitTier::classify(1, 0, 1));
    }

    #[Test]
    public function classify_small(): void
    {
        $this->assertSame(WorkUnitTier::Small, WorkUnitTier::classify(3, 1, 1));
    }

    #[Test]
    public function classify_medium(): void
    {
        $this->assertSame(WorkUnitTier::Medium, WorkUnitTier::classify(5, 2, 3));
    }

    #[Test]
    public function classify_large(): void
    {
        $this->assertSame(WorkUnitTier::Large, WorkUnitTier::classify(10, 3, 5));
    }

    #[Test]
    public function work_unit_is_blocked_by_incomplete_dependency(): void
    {
        $unit = new WorkUnit(
            id: 'u2',
            name: 'Add validation',
            rfcSections: ['Validation'],
            description: 'Add input validation',
            deps: ['u1'],
        );

        $this->assertTrue($unit->isBlockedBy(['u1']));
        $this->assertFalse($unit->isBlockedBy(['u3']));
    }

    #[Test]
    public function work_unit_with_no_deps_is_never_blocked(): void
    {
        $unit = new WorkUnit(
            id: 'u1',
            name: 'Setup',
            rfcSections: ['Setup'],
            description: 'Initial setup',
        );

        $this->assertFalse($unit->isBlockedBy(['u0', 'u2', 'u3']));
    }

    #[Test]
    public function to_array_includes_tier_and_stages(): void
    {
        $unit = new WorkUnit(
            id: 'u1',
            name: 'Add auth',
            rfcSections: ['Auth'],
            description: 'Add authentication',
            deps: [],
            acceptance: ['Login works', 'Token refresh'],
            tier: WorkUnitTier::Medium,
        );

        $arr = $unit->toArray();

        $this->assertSame('u1', $arr['id']);
        $this->assertSame('Add auth', $arr['name']);
        $this->assertSame(['Auth'], $arr['rfc_sections']);
        $this->assertSame([], $arr['deps']);
        $this->assertSame(['Login works', 'Token refresh'], $arr['acceptance']);
        $this->assertSame('medium', $arr['tier']);
        $this->assertSame(5, $arr['pipeline_stages']);
    }

    #[Test]
    public function default_tier_is_small(): void
    {
        $unit = new WorkUnit(
            id: 'u1',
            name: 'Test',
            rfcSections: ['X'],
            description: 'desc',
        );

        $this->assertSame(WorkUnitTier::Small, $unit->tier);
    }

    #[Test]
    public function dependency_chain_forms_a_dag(): void
    {
        // u1 (no deps) → u2 (deps: u1) → u3 (deps: u2)
        // u4 (deps: u1) runs in parallel with u2 after u1 completes
        $u1 = new WorkUnit('u1', 'Setup', ['S'], 'Setup');
        $u2 = new WorkUnit('u2', 'Build', ['B'], 'Build', deps: ['u1']);
        $u3 = new WorkUnit('u3', 'Test', ['T'], 'Test', deps: ['u2']);
        $u4 = new WorkUnit('u4', 'Docs', ['D'], 'Docs', deps: ['u1']);

        // u1 is ready (no blockers)
        $this->assertFalse($u1->isBlockedBy([]));

        // After u1 completes, u2 and u4 are ready, u3 is still blocked
        $this->assertFalse($u2->isBlockedBy([])); // u1 done, not in incomplete list
        $this->assertFalse($u4->isBlockedBy([]));
        $this->assertTrue($u3->isBlockedBy(['u2'])); // u2 still incomplete
    }
}