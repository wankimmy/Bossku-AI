<?php

namespace App\Services\BosskuAi\Ralphinho;

/**
 * Complexity tiers for WorkUnits. Ported from ECC's Ralphinho RFC-driven DAG.
 * The tier determines the pipeline depth: how many stages the WorkUnit passes
 * through before merging. Trivial work gets one stage; large work gets seven+.
 *
 * The tier-driven depth prevents over-processing small work and
 * under-processing large work. Each stage runs in a separate context window
 * (Author-Bias Elimination): the stage-N agent sees only the RFC + the prior
 * stage's output, not the full conversation.
 */
enum WorkUnitTier: string
{
    case Trivial = 'trivial';

    case Small = 'small';

    case Medium = 'medium';

    case Large = 'large';

    /** The number of pipeline stages this tier runs through. */
    public function pipelineStages(): int
    {
        return match ($this) {
            self::Trivial => 1, // plan → execute → done (straight-through)
            self::Small => 3,   // plan → execute → review
            self::Medium => 5,  // plan → execute → test → review → refactor
            self::Large => 7,   // plan → design → execute → test → review → security-audit → refactor
        };
    }

    /** The stage names this tier runs through, in order. */
    public function stages(): array
    {
        return match ($this) {
            self::Trivial => ['plan', 'execute'],
            self::Small => ['plan', 'execute', 'review'],
            self::Medium => ['plan', 'execute', 'test', 'review', 'refactor'],
            self::Large => ['plan', 'design', 'execute', 'test', 'review', 'security-audit', 'refactor'],
        };
    }

    /**
     * Classify a WorkUnit into a tier based on heuristics: number of files,
     * number of dependencies, and acceptance-criteria count.
     *
     * @param  int  $fileCount  estimated files touched
     * @param  int  $depCount  number of dependencies
     * @param  int  $acceptanceCount  number of acceptance criteria
     */
    public static function classify(int $fileCount, int $depCount, int $acceptanceCount): self
    {
        $score = $fileCount + ($depCount * 2) + $acceptanceCount;

        return match (true) {
            $score <= 2 => self::Trivial,
            $score <= 6 => self::Small,
            $score <= 12 => self::Medium,
            default => self::Large,
        };
    }
}