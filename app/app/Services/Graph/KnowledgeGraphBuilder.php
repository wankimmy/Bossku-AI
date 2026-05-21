<?php

namespace App\Services\Graph;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\Skill;

class KnowledgeGraphBuilder
{
    public function rebuild(): void
    {
        GraphEdge::query()->delete();
        GraphNode::query()->delete();

        $skillNodes = [];
        foreach (Skill::all() as $skill) {
            $node = GraphNode::create([
                'type'        => 'skill',
                'label'       => $skill->name,
                'source_type' => 'skill',
                'source_id'   => $skill->getKey(),
                'confidence'  => $skill->confidence ?? null,
                'properties'  => [
                    'usage_count'   => $skill->usage_count,
                    'quality_score' => $skill->quality_score,
                ],
            ]);
            $skillNodes[$skill->name] = $node;
        }

        $runNodes = [];
        $recentRuns = Run::latest()->limit(50)->get();
        foreach ($recentRuns as $run) {
            $node = GraphNode::create([
                'type'        => 'run',
                'label'       => 'Run ' . $run->getKey(),
                'source_type' => 'run',
                'source_id'   => $run->getKey(),
                'properties'  => [
                    'status'      => $run->status,
                    'audit_score' => $run->audit_score,
                    'skill_name'  => $run->selected_skill_name,
                ],
            ]);
            $runNodes[$run->getKey()] = $node;
        }

        foreach (Memory::all() as $memory) {
            GraphNode::create([
                'type'        => 'memory',
                'label'       => $memory->human_summary ?? \Illuminate\Support\Str::limit((string) $memory->content, 80),
                'source_type' => 'memory',
                'source_id'   => $memory->getKey(),
                'confidence'  => $memory->confidence,
                'properties'  => [
                    'type'       => $memory->type,
                    'is_active'  => $memory->is_active,
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
            GraphEdge::create([
                'source_node_id' => $runNodes[$run->getKey()]->getKey(),
                'target_node_id' => $skillNodes[$skillName]->getKey(),
                'relation'       => 'used_in',
                'weight'         => 1.0,
            ]);
        }
    }

    public function buildForRun(Run $run): void
    {
        $existing = GraphNode::where('source_type', 'run')
            ->where('source_id', $run->getKey())
            ->first();

        if ($existing) {
            $existing->update([
                'properties' => [
                    'status'      => $run->status,
                    'audit_score' => $run->audit_score,
                    'skill_name'  => $run->selected_skill_name,
                ],
            ]);
            $runNode = $existing;
        } else {
            $runNode = GraphNode::create([
                'type'        => 'run',
                'label'       => 'Run ' . $run->getKey(),
                'source_type' => 'run',
                'source_id'   => $run->getKey(),
                'properties'  => [
                    'status'      => $run->status,
                    'audit_score' => $run->audit_score,
                    'skill_name'  => $run->selected_skill_name,
                ],
            ]);
        }

        $skillName = $run->selected_skill_name;
        if (blank($skillName)) {
            return;
        }

        $skillNode = GraphNode::where('source_type', 'skill')
            ->whereJsonContains('properties->name', $skillName)
            ->orWhere('label', $skillName)
            ->where('source_type', 'skill')
            ->first();

        if (! $skillNode) {
            $skill = Skill::where('name', $skillName)->first();
            if ($skill) {
                $skillNode = GraphNode::firstOrCreate(
                    ['source_type' => 'skill', 'source_id' => $skill->getKey()],
                    [
                        'type'       => 'skill',
                        'label'      => $skill->name,
                        'properties' => ['usage_count' => $skill->usage_count],
                    ]
                );
            }
        }

        if ($skillNode) {
            $edgeExists = GraphEdge::where('source_node_id', $runNode->getKey())
                ->where('target_node_id', $skillNode->getKey())
                ->where('relation', 'used_in')
                ->exists();

            if (! $edgeExists) {
                GraphEdge::create([
                    'source_node_id' => $runNode->getKey(),
                    'target_node_id' => $skillNode->getKey(),
                    'relation'       => 'used_in',
                    'weight'         => 1.0,
                ]);
            }
        }
    }
}
