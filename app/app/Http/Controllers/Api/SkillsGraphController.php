<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SkillLink;

class SkillsGraphController extends Controller
{
    public function index()
    {
        $nodes = Skill::query()
            ->get(['id', 'name', 'description', 'quality_score', 'is_active', 'usage_count'])
            ->map(fn ($s) => [
                'id'            => $s->id,
                'name'          => $s->name,
                'description'   => $s->description,
                'quality_score' => $s->quality_score,
                'is_active'     => $s->is_active,
                'usage_count'   => $s->usage_count,
            ]);

        $edges = SkillLink::query()
            ->get(['id', 'skill_id', 'link_type', 'linked_id', 'metadata'])
            ->map(fn ($l) => [
                'id'         => $l->id,
                'source'     => $l->skill_id,
                'target'     => $l->linked_id,
                'link_type'  => $l->link_type,
            ]);

        return response()->json([
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    public function rebuild()
    {
        /** @var \App\Services\Skills\SkillsGraphBuilder $builder */
        $builder = app(\App\Services\Skills\SkillsGraphBuilder::class);
        $builder->rebuild();

        return response()->json(['message' => 'Skills graph rebuilt.']);
    }
}
