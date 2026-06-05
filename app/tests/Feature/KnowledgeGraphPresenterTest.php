<?php

namespace Tests\Feature;

use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeGraphPresenterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function knowledge_graph_returns_normalized_d3_shape(): void
    {
        $a = GraphNode::create([
            'type' => 'skill',
            'label' => 'cofounder',
            'source_type' => 'skill',
            'confidence' => 0.9,
        ]);
        $b = GraphNode::create([
            'type' => 'run',
            'label' => 'Run abc',
            'source_type' => 'run',
        ]);

        GraphEdge::create([
            'source_node_id' => $b->getKey(),
            'target_node_id' => $a->getKey(),
            'relation' => 'used_in',
        ]);

        $this->getJson('/api/knowledge-graph')
            ->assertOk()
            ->assertJsonPath('node_count', 2)
            ->assertJsonPath('edges.0.kind', 'used_in')
            ->assertJsonStructure([
                'nodes' => [['id', 'label', 'category', 'depth']],
                'edges' => [['source', 'target', 'kind']],
            ]);
    }
}
