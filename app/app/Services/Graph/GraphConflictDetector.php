<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Skill;

class GraphConflictDetector
{
    public function detect(): array
    {
        $conflicts = [];

        $skills    = Skill::all();
        $namesSeen = [];

        foreach ($skills as $skill) {
            $normalised = mb_strtolower(trim((string) $skill->name));
            if (isset($namesSeen[$normalised])) {
                $conflicts[] = 'Duplicate skill name detected: "' . $skill->name . '" (id: ' . $skill->getKey() . ') conflicts with id: ' . $namesSeen[$normalised];
                $this->markNodeConflict('skill', $skill->getKey());
            } else {
                $namesSeen[$normalised] = $skill->getKey();
            }

            $similar = $skills->filter(function (Skill $other) use ($skill, $normalised): bool {
                if ($other->getKey() === $skill->getKey()) {
                    return false;
                }
                $otherNorm = mb_strtolower(trim((string) $other->name));
                similar_text($normalised, $otherNorm, $pct);

                return $pct >= 85 && $normalised !== $otherNorm;
            });

            foreach ($similar as $sim) {
                $conflicts[] = 'Similar skill names: "' . $skill->name . '" and "' . $sim->name . '" may be duplicates.';
                $this->markNodeConflict('skill', $skill->getKey());
                $this->markNodeConflict('skill', $sim->getKey());
            }
        }

        $thirtyDaysAgo = now()->subDays(30);
        foreach ($skills as $skill) {
            if ((int) ($skill->usage_count ?? 0) === 0 && $skill->created_at <= $thirtyDaysAgo) {
                $conflicts[] = 'Unused skill "' . $skill->name . '" (id: ' . $skill->getKey() . ') has not been used since creation over 30 days ago.';
                $this->markNodeConflict('skill', $skill->getKey());
            }
        }

        foreach ($skills as $skill) {
            if ((float) ($skill->quality_score ?? 0) < 40) {
                $conflicts[] = 'Weak skill "' . $skill->name . '" (id: ' . $skill->getKey() . ') has quality score ' . $skill->quality_score . ' (threshold: 40).';
                $this->markNodeConflict('skill', $skill->getKey());
            }
        }

        return $conflicts;
    }

    private function markNodeConflict(string $sourceType, string $sourceId): void
    {
        GraphNode::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->update(['has_conflict' => true]);
    }
}
