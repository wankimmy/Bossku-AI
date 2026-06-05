<?php

namespace Tests\Unit;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Models\BosskuAi\Skill;
use App\Services\Graph\KnowledgeGraphBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeGraphDedupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function build_for_run_twice_does_not_duplicate_nodes_or_edges(): void
    {
        $skill = Skill::create([
            'name' => 'incremental-skill',
            'description' => 'Skill for incremental graph build tests.',
            'content' => 'Test content for incremental-skill.',
        ]);

        $run = \App\Models\BosskuAi\Run::create([
            'prompt' => 'test',
            'status' => 'completed',
            'selected_skill_name' => $skill->name,
        ]);

        $builder = app(KnowledgeGraphBuilder::class);
        $builder->buildForRun($run);
        $builder->buildForRun($run->fresh());

        $this->assertSame(1, GraphNode::where('source_type', 'skill')->where('source_id', $skill->getKey())->count());
        $this->assertSame(1, GraphNode::where('source_type', 'run')->where('source_id', $run->getKey())->count());
        $this->assertSame(1, GraphEdge::where('relation', 'used_in')->count());
    }

    #[Test]
    public function rebuild_upserts_without_duplicating_skill_nodes(): void
    {
        $skill = Skill::create([
            'name' => 'dedup-skill',
            'description' => 'Skill used for rebuild deduplication tests.',
            'content' => 'Test content for dedup-skill.',
        ]);

        GraphNode::create([
            'type' => 'skill',
            'label' => 'old label',
            'source_type' => 'skill',
            'source_id' => $skill->getKey(),
        ]);

        app(KnowledgeGraphBuilder::class)->rebuild();

        $this->assertSame(1, GraphNode::where('source_type', 'skill')->where('source_id', $skill->getKey())->count());
        $this->assertSame('dedup-skill', GraphNode::where('source_id', $skill->getKey())->value('label'));
    }
}
