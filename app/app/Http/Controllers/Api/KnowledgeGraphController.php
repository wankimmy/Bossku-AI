<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BosskuAi\GraphEdge;
use App\Models\BosskuAi\GraphNode;
use App\Services\Graph\KnowledgeGraphBuilder;
use App\Services\Graph\KnowledgeGraphPresenter;

class KnowledgeGraphController extends Controller
{
    public function __construct(
        private readonly KnowledgeGraphPresenter $presenter
    ) {}

    public function index()
    {
        return response()->json($this->presenter->present());
    }

    public function rebuild()
    {
        $builder = app(KnowledgeGraphBuilder::class);
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
