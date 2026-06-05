<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use App\Services\Skills\SkillQualityScorer;

class SkillsGraphBuilder
{
    public function __construct(
        private readonly SkillQualityScorer $scorer,
        private readonly KnowledgeGraphDedup $dedup,
    ) {}

    public function rebuild(): void
    {
        GraphNode::where('source_type', 'skill')->each(function (GraphNode $node) {
            GraphEdge::where('source_node_id', $node->getKey())
                ->orWhere('target_node_id', $node->getKey())
                ->delete();
            $node->delete();
        });

        $skills    = Skill::all();
        $nodeMap   = [];

        foreach ($skills as $skill) {
            $quality = $this->scorer->score($skill);
            $node = GraphNode::updateOrCreate(
                [
                    'source_type' => 'skill',
                    'source_id'   => $skill->getKey(),
                ],
                [
                    'type'        => 'skill',
                    'label'       => $skill->name,
                    'confidence'  => $skill->confidence ?? null,
                    'properties'  => [
                        'quality_score' => $quality,
                        'usage_count'   => $skill->usage_count,
                        'tags'          => $skill->tags ?? [],
                        'is_active'     => $skill->is_active,
                    ],
                ],
            );
            $nodeMap[$skill->getKey()] = ['node' => $node, 'skill' => $skill];
        }

        $skillIds  = $skills->pluck('id')->all();
        $skillList = $skills->all();

        foreach ($skillList as $i => $skillA) {
            $tagsA = (array) ($skillA->tags ?? []);

            for ($j = $i + 1; $j < count($skillList); $j++) {
                $skillB = $skillList[$j];
                $tagsB  = (array) ($skillB->tags ?? []);

                $sharedTags = array_intersect($tagsA, $tagsB);
                if (! empty($sharedTags)) {
                    GraphEdge::firstOrCreate(
                        [
                            'source_node_id' => $nodeMap[$skillA->getKey()]['node']->getKey(),
                            'target_node_id' => $nodeMap[$skillB->getKey()]['node']->getKey(),
                            'relation'       => 'shares_tags',
                        ],
                        [
                            'weight'     => count($sharedTags),
                            'properties' => ['shared_tags' => array_values($sharedTags)],
                        ],
                    );
                }
            }
        }

        $runs = Run::whereNotNull('selected_skill_name')->get();
        $coUsage = [];

        foreach ($runs as $run) {
            $skillsInRun = Skill::whereIn('name', [$run->selected_skill_name])->pluck('id')->all();
            if (count($skillsInRun) < 2) {
                continue;
            }
            sort($skillsInRun);
            $key = implode('|', $skillsInRun);
            $coUsage[$key] = ($coUsage[$key] ?? 0) + 1;
        }

        foreach ($coUsage as $key => $count) {
            [$idA, $idB] = explode('|', $key);
            if (! isset($nodeMap[$idA], $nodeMap[$idB])) {
                continue;
            }
            GraphEdge::firstOrCreate(
                [
                    'source_node_id' => $nodeMap[$idA]['node']->getKey(),
                    'target_node_id' => $nodeMap[$idB]['node']->getKey(),
                    'relation'       => 'used_together',
                ],
                ['weight' => $count]
            );
        }

        $this->dedup->prune();
    }
}
