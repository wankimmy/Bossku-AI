<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;
use Illuminate\Support\Str;

class KnowledgeGraphBuilder
{
    public function __construct(
        private readonly KnowledgeGraphDedup $dedup,
    ) {}

    public function rebuild(): void
    {
        GraphEdge::query()->delete();
        GraphNode::query()->delete();

        $skillNodes = [];
        foreach (Skill::all() as $skill) {
            $node = $this->upsertNode('skill', (string) $skill->getKey(), [
                'type' => 'skill',
                'label' => $skill->name,
                'source_type' => 'skill',
                'source_id' => $skill->getKey(),
                'confidence' => $skill->confidence ?? null,
                'properties' => [
                    'usage_count' => $skill->usage_count,
                    'quality_score' => $skill->quality_score,
                ],
            ]);
            $skillNodes[$skill->name] = $node;
        }

        $runNodes = [];
        $recentRuns = Run::latest()->limit(50)->get();
        foreach ($recentRuns as $run) {
            $node = $this->upsertNode('run', (string) $run->getKey(), [
                'type' => 'run',
                'label' => 'Run '.$run->getKey(),
                'source_type' => 'run',
                'source_id' => $run->getKey(),
                'properties' => [
                    'status' => $run->status,
                    'audit_score' => $run->audit_score,
                    'skill_name' => $run->selected_skill_name,
                ],
            ]);
            $runNodes[$run->getKey()] = $node;
        }

        foreach (Memory::all() as $memory) {
            $this->upsertNode('memory', (string) $memory->getKey(), [
                'type' => 'memory',
                'label' => $memory->human_summary ?? Str::limit((string) $memory->content, 80),
                'source_type' => 'memory',
                'source_id' => $memory->getKey(),
                'confidence' => $memory->confidence,
                'properties' => [
                    'type' => $memory->type,
                    'is_active' => $memory->is_active,
                    'usage_count' => $memory->usage_count,
                ],
            ]);
        }

        foreach ($recentRuns as $run) {
            $skillName = $run->selected_skill_name;
            if (blank($skillName)) {
                continue;
            }
            if (! isset($skillNodes[$skillName], $runNodes[$run->getKey()])) {
                continue;
            }
            $this->upsertEdge(
                (string) $runNodes[$run->getKey()]->getKey(),
                (string) $skillNodes[$skillName]->getKey(),
                'used_in',
                1.0,
            );
        }

        $this->dedup->prune();
    }

    public function buildForRun(Run $run): void
    {
        $runNode = $this->upsertNode('run', (string) $run->getKey(), [
            'type' => 'run',
            'label' => 'Run '.$run->getKey(),
            'source_type' => 'run',
            'source_id' => $run->getKey(),
            'properties' => [
                'status' => $run->status,
                'audit_score' => $run->audit_score,
                'skill_name' => $run->selected_skill_name,
            ],
        ]);

        $skillName = $run->selected_skill_name;
        if (blank($skillName)) {
            return;
        }

        $skill = Skill::where('name', $skillName)->first();
        if (! $skill) {
            return;
        }

        $skillNode = $this->upsertNode('skill', (string) $skill->getKey(), [
            'type' => 'skill',
            'label' => $skill->name,
            'source_type' => 'skill',
            'source_id' => $skill->getKey(),
            'confidence' => $skill->confidence ?? null,
            'properties' => [
                'usage_count' => $skill->usage_count,
                'quality_score' => $skill->quality_score,
            ],
        ]);

        $this->upsertEdge(
            (string) $runNode->getKey(),
            (string) $skillNode->getKey(),
            'used_in',
            1.0,
        );

        $this->dedup->prune();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertNode(string $type, string $sourceId, array $attributes): GraphNode
    {
        if ($sourceId !== '') {
            return GraphNode::updateOrCreate(
                [
                    'source_type' => $type,
                    'source_id' => $sourceId,
                ],
                $attributes,
            );
        }

        return GraphNode::create($attributes);
    }

    protected function upsertEdge(
        string $sourceNodeId,
        string $targetNodeId,
        string $relation,
        float $weight = 1.0,
    ): GraphEdge {
        return GraphEdge::firstOrCreate(
            [
                'source_node_id' => $sourceNodeId,
                'target_node_id' => $targetNodeId,
                'relation' => $relation,
            ],
            [
                'weight' => $weight,
                'is_conflict' => false,
            ],
        );
    }
}
