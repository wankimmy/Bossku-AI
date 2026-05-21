<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;

class KnowledgeGraphController extends Controller
{
    public function index()
    {
        return response()->json([
            'nodes' => GraphNode::all(),
            'edges' => GraphEdge::all(),
        ]);
    }

    public function rebuild()
    {
        /** @var \App\Services\Knowledge\KnowledgeGraphBuilder $builder */
        $builder = app(\App\Services\Knowledge\KnowledgeGraphBuilder::class);
        $builder->rebuild();

        return response()->json([
            'message'     => 'Knowledge graph rebuilt.',
            'node_count'  => GraphNode::count(),
            'edge_count'  => GraphEdge::count(),
        ]);
    }

    public function node(string $id)
    {
        $node = GraphNode::with(['outgoingEdges', 'incomingEdges'])->findOrFail($id);

        return response()->json($node);
    }
}
