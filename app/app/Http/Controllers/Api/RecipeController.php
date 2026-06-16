<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Recipes\Recipe;
use App\Services\Recipes\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Parameterized, shareable workflow recipes (goose-style). File-first under
 * `recipes/`; rendered + scanned, then run through the orchestrator/kernel.
 */
class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $recipes) {}

    public function index(): JsonResponse
    {
        $data = array_map(static fn (Recipe $r): array => [
            'slug' => $r->slug,
            'title' => $r->title,
            'description' => $r->description,
            'workflow' => $r->workflow,
            'parameters' => count($r->parameters),
        ], $this->recipes->all());

        return response()->json(['data' => $data]);
    }

    public function show(string $slug): JsonResponse
    {
        try {
            return response()->json($this->recipes->get($slug)->toArray());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /** POST /api/recipes/{slug}/preview — validate, render, and scan. */
    public function preview(string $slug, Request $request): JsonResponse
    {
        try {
            $result = $this->recipes->preview($slug, (array) $request->input('parameters', []));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $status = $result['errors'] === [] ? 200 : 422;

        return response()->json($result, $status);
    }

    /** POST /api/recipes/{slug}/run — render + dispatch through the orchestrator. */
    public function run(string $slug, Request $request, OrchestratorService $orchestrator): JsonResponse
    {
        try {
            $result = $this->recipes->run($slug, (array) $request->input('parameters', []), $orchestrator);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }
}
