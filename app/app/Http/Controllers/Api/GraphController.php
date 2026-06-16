<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Kernel\Graph\GraphRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Graph introspection for the Studio: returns the topology (nodes, edges,
 * branches) a visual renderer needs. Combine with /runs/{id}/checkpoints and the
 * SSE stream to overlay a live run, scrub checkpoints, and fork.
 */
class GraphController extends Controller
{
    public function __construct(private readonly GraphRegistry $graphs) {}

    public function index(): JsonResponse
    {
        return response()->json(['graphs' => $this->graphs->names()]);
    }

    public function show(string $name): JsonResponse
    {
        $topology = $this->graphs->topology($name);
        if ($topology === null) {
            return response()->json(['message' => "Unknown graph: {$name}"], 404);
        }

        return response()->json($topology);
    }
}
